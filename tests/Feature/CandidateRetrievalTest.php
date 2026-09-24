<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Like;
use App\Models\MatchScore;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Services\CandidateRetrievalService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateRetrievalTest extends TestCase
{
    use RefreshDatabase;

    protected function viewer(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'latitude' => -6.2, 'longitude' => 106.8, 'city' => 'Jakarta',
        ], $attrs));
    }

    public function test_age_gender_city_goal_filters(): void
    {
        $me = $this->viewer();
        $ok = User::factory()->create([
            'gender' => 'female', 'city' => 'Bandung',
            'date_of_birth' => now()->subYears(25)->toDateString(),
        ]);
        $ok->profile()->updateOrCreate([], ['relationship_goal' => 'marriage']);
        $wrongCity = User::factory()->create(['gender' => 'female', 'city' => 'Medan', 'date_of_birth' => now()->subYears(25)->toDateString()]);
        $wrongCity->profile()->updateOrCreate([], ['relationship_goal' => 'marriage']);
        $tooOld = User::factory()->create(['gender' => 'female', 'city' => 'Bandung', 'date_of_birth' => now()->subYears(60)->toDateString()]);
        $tooOld->profile()->updateOrCreate([], ['relationship_goal' => 'marriage']);

        $ids = app(CandidateRetrievalService::class)
            ->pool($me, ['gender' => 'female', 'city' => 'Bandung', 'min_age' => 20, 'max_age' => 30, 'relationship_goal' => 'marriage'])
            ->pluck('users.id')->all();

        $this->assertContains($ok->id, $ids);
        $this->assertNotContains($wrongCity->id, $ids);
        $this->assertNotContains($tooOld->id, $ids);
        $this->assertNotContains($me->id, $ids);
    }

    public function test_distance_bbox_and_verification_flags(): void
    {
        $me = $this->viewer();
        $near = User::factory()->create(['latitude' => -6.21, 'longitude' => 106.81, 'is_verified' => true]);
        $far = User::factory()->create(['latitude' => 3.6, 'longitude' => 98.67]);

        $ids = app(CandidateRetrievalService::class)
            ->pool($me, ['max_distance_km' => 50, 'verified' => true])
            ->pluck('users.id')->all();

        $this->assertContains($near->id, $ids);
        $this->assertNotContains($far->id, $ids);
    }

    public function test_privacy_blocked_and_incognito(): void
    {
        $me = $this->viewer();
        $blocked = User::factory()->create();
        Block::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);
        $blockedMe = User::factory()->create();
        Block::create(['blocker_id' => $blockedMe->id, 'blocked_id' => $me->id]);
        $ghost = User::factory()->create();
        $ghost->profilePrivacy()->updateOrCreate([], ['is_incognito' => true]);
        $liker = User::factory()->create();
        $liker->profilePrivacy()->updateOrCreate([], ['is_incognito' => true]);
        Like::create(['liker_id' => $liker->id, 'liked_id' => $me->id]);

        $ids = app(CandidateRetrievalService::class)->pool($me, [])->pluck('users.id')->all();

        $this->assertNotContains($blocked->id, $ids);
        $this->assertNotContains($blockedMe->id, $ids);
        $this->assertNotContains($ghost->id, $ids);
        $this->assertContains($liker->id, $ids);
    }

    public function test_reports_do_not_change_retrieval_semantics(): void
    {
        // Preserved behavior: reports route to moderation, they do not hide
        // anyone from candidate retrieval (only blocks do).
        $me = $this->viewer();
        $reported = User::factory()->create();
        Report::create(['reporter_id' => $me->id, 'reported_user_id' => $reported->id, 'reason' => 'other', 'details' => 't', 'status' => 'pending']);

        $ids = app(CandidateRetrievalService::class)->pool($me, [])->pluck('users.id')->all();
        $this->assertContains($reported->id, $ids);
    }

    public function test_exclude_liked_option_and_exclude_ids(): void
    {
        $me = $this->viewer();
        $liked = User::factory()->create();
        Like::create(['liker_id' => $me->id, 'liked_id' => $liked->id]);
        $other = User::factory()->create();

        $svc = app(CandidateRetrievalService::class);
        $withLikes = $svc->pool($me, [], null, ['excludeLiked' => false])->pluck('users.id')->all();
        $this->assertContains($liked->id, $withLikes);

        $withoutLikes = $svc->pool($me, [], null, ['excludeLiked' => true])->pluck('users.id')->all();
        $this->assertNotContains($liked->id, $withoutLikes);
        $this->assertContains($other->id, $withoutLikes);

        $excluded = $svc->pool($me, ['exclude_ids' => [$other->id]])->pluck('users.id')->all();
        $this->assertNotContains($other->id, $excluded);
    }

    public function test_preference_defaults_and_empty_results(): void
    {
        $me = $this->viewer();
        $me->partnerPreference()->updateOrCreate([], ['gender_preference' => 'female', 'min_age' => 20, 'max_age' => 30]);
        $f = User::factory()->create(['gender' => 'female', 'date_of_birth' => now()->subYears(25)->toDateString()]);
        $m = User::factory()->create(['gender' => 'male', 'date_of_birth' => now()->subYears(25)->toDateString()]);

        $ids = app(CandidateRetrievalService::class)->pool($me->fresh(), [], null, ['preferenceDefaults' => true])->pluck('users.id')->all();
        $this->assertContains($f->id, $ids);
        $this->assertNotContains($m->id, $ids);

        // Impossible filter → empty, never an error.
        $none = app(CandidateRetrievalService::class)->pool($me->fresh(), ['city' => 'Tidak Ada Kota Ini'])->pluck('users.id')->all();
        $this->assertSame([], $none);

        // Inactive users never appear.
        $inactive = User::factory()->create(['status' => 'banned']);
        $all = app(CandidateRetrievalService::class)->pool($me->fresh(), [])->pluck('users.id')->all();
        $this->assertNotContains($inactive->id, $all);
    }

    public function test_stale_scores_recomputed_and_cached_reused(): void
    {
        $a = $this->viewer();
        $b = User::factory()->create();
        $engine = app(MatchingEngine::class);

        $first = $engine->scoreMany($a, collect([$b]));
        $this->assertArrayHasKey($b->id, $first);
        $oldAt = MatchScore::first()->computed_at;

        // Fresh rows are reused (no recompute).
        $engine->scoreMany($a, User::where('id', $b->id)->get());
        $this->assertEquals($oldAt->toDateTimeString(), MatchScore::first()->computed_at->toDateTimeString());

        // Stale rows (beyond TTL) are recomputed.
        MatchScore::query()->update(['computed_at' => now()->subDays(30)]);
        $second = $engine->scoreMany($a, User::where('id', $b->id)->get());
        $this->assertArrayHasKey($b->id, $second);
        $this->assertTrue(MatchScore::first()->computed_at->gt(now()->subDay()));
    }

    public function test_cold_start_user_gets_candidates_without_errors(): void
    {
        // Brand-new user: no answers, no interests, bare profile.
        $me = User::factory()->create(['latitude' => -6.2, 'longitude' => 106.8]);
        User::factory()->count(5)->create();
        $engine = app(MatchingEngine::class);

        $cands = $engine->candidatesFor($me->fresh(), [], 5);
        $this->assertNotEmpty($cands);
        foreach ($cands as $c) {
            $this->assertArrayHasKey($c->id, $engine->scoreMany($me->fresh(), collect([$c])));
        }
    }

    public function test_weights_version_bump_changes_weights(): void
    {
        $before = app(MatchingEngine::class)->weights();
        Setting::updateOrCreate(['key' => 'matchmaking.version'], ['value' => '999', 'group' => 'matchmaking']);
        Setting::updateOrCreate(['key' => 'match.weight.age'], ['value' => '90', 'group' => 'matchmaking']);
        Setting::clearCache();
        // Fresh instance, as in a new request/worker job.
        $after = app(MatchingEngine::class)->weights();
        $this->assertGreaterThan($before['age'], $after['age']);
    }
}
