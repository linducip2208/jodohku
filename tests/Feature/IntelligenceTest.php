<?php

namespace Tests\Feature;

use App\Jobs\SendMatchReminders;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Courtship;
use App\Models\MatchNote;
use App\Models\MessageBookmark;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\ChatService;
use App\Services\MatchExplanation;
use App\Services\NotificationService;
use App\Services\PersonalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function matchedPair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        $match = UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()->subDays(5)]);

        return [$a->fresh(), $b->fresh(), $match];
    }

    public function test_match_explanation_structure_and_privacy(): void
    {
        [$a, $b] = [$this->matchedPair()[0], $this->matchedPair()[1]];
        $why = app(MatchExplanation::class)->for($a, $b);

        $this->assertArrayHasKey('overall', $why);
        $this->assertArrayHasKey('strength', $why);
        $this->assertCount(8, $why['dimensions']);
        $this->assertEquals(['Usia', 'Lokasi', 'Preferensi', 'Kepribadian', 'Minat', 'Gaya Hidup', 'Tujuan', 'Aktivitas'], array_column($why['dimensions'], 'label'));
        $blob = json_encode($why);
        $this->assertStringNotContainsString($a->email, $blob);
        $this->assertStringNotContainsString($b->email, $blob);
        $this->assertStringNotContainsString((string) $b->phone, $blob);
    }

    public function test_match_notes_private_per_author(): void
    {
        [$a, $b, $match] = $this->matchedPair();
        $stranger = User::factory()->create();

        // Stranger (no active match): 404, no IDOR signal difference from missing.
        $this->actingAs($stranger)->getJson("/api/v1/matches/{$a->id}/note")->assertNotFound();
        $this->actingAs($stranger)->putJson("/api/v1/matches/{$a->id}/note", ['body' => 'x'])->assertNotFound();

        $this->actingAs($a)->putJson("/api/v1/matches/{$b->id}/note", ['body' => 'Baik, serius, satu kota.'])
            ->assertCreated()->assertJsonPath('body', 'Baik, serius, satu kota.');
        $this->actingAs($a)->getJson("/api/v1/matches/{$b->id}/note")
            ->assertOk()->assertJsonPath('body', 'Baik, serius, satu kota.');
        // B cannot see A's private note.
        $this->actingAs($b)->getJson("/api/v1/matches/{$a->id}/note")
            ->assertOk()->assertJsonMissing(['body' => 'Baik, serius, satu kota.']);
        $this->assertEquals(1, MatchNote::count());

        // Empty body clears the note.
        $this->actingAs($a)->putJson("/api/v1/matches/{$b->id}/note", ['body' => ''])->assertOk();
        $this->assertEquals(0, MatchNote::count());
    }

    public function test_bookmark_requires_membership(): void
    {
        [$a, $b] = [$this->matchedPair()[0], $this->matchedPair()[1]];
        $chat = app(ChatService::class);
        $conv = $chat->findOrCreateDirect($a, $b);
        $msg = $chat->sendMessage($conv, $a, ['body' => 'Halo, simpan ini ya']);
        $outsider = User::factory()->create();

        $this->actingAs($b)->postJson("/api/v1/messages/{$msg->id}/bookmark")->assertCreated();
        $this->assertTrue(MessageBookmark::where('message_id', $msg->id)->where('user_id', $b->id)->exists());
        $this->actingAs($outsider)->postJson("/api/v1/messages/{$msg->id}/bookmark")->assertForbidden();
        $this->actingAs($b)->deleteJson("/api/v1/messages/{$msg->id}/bookmark")->assertOk();
        $this->assertFalse(MessageBookmark::where('message_id', $msg->id)->where('user_id', $b->id)->exists());

        $this->expectException(\RuntimeException::class);
        $chat->bookmark($msg, $outsider);
    }

    public function test_match_reminders_only_stagnant_and_respect_prefs(): void
    {
        [$a, $b, $match] = $this->matchedPair();
        $match->update(['matched_at' => now()->subDays(10)]);

        app(SendMatchReminders::class)->handle(app(NotificationService::class));
        $this->assertEquals(1, $a->fresh()->unreadNotifications()->where('type', 'App\\Notifications\\MatchNudge')->count());
        $this->assertEquals(1, $b->fresh()->unreadNotifications()->where('type', 'App\\Notifications\\MatchNudge')->count());

        // Second run dedupes (no stacking).
        app(SendMatchReminders::class)->handle(app(NotificationService::class));
        $this->assertEquals(1, $a->fresh()->unreadNotifications()->where('type', 'App\\Notifications\\MatchNudge')->count());

        // A conversation with messages silences nudges for that match.
        [$c, $d, $match2] = $this->matchedPair();
        $match2->update(['matched_at' => now()->subDays(10)]);
        $chat = app(ChatService::class);
        $conv = $chat->findOrCreateDirect($c, $d);
        $chat->sendMessage($conv, $c, ['body' => 'Halo!']);
        app(SendMatchReminders::class)->handle(app(NotificationService::class));
        $this->assertEquals(0, $c->fresh()->unreadNotifications()->where('type', 'App\\Notifications\\MatchNudge')->count());
        $this->assertEquals(0, $d->fresh()->unreadNotifications()->where('type', 'App\\Notifications\\MatchNudge')->count());

        // Opt-out is respected on a stagnant match: e gets nothing, f does.
        [$e, $f, $match3] = $this->matchedPair();
        $match3->update(['matched_at' => now()->subDays(10)]);
        $e->notificationPreference()->updateOrCreate([], ['push_matches' => false]);
        app(SendMatchReminders::class)->handle(app(NotificationService::class));
        $this->assertEquals(0, $e->fresh()->unreadNotifications()->where('type', 'App\\Notifications\\MatchNudge')->count());
        $this->assertEquals(1, $f->fresh()->unreadNotifications()->where('type', 'App\\Notifications\\MatchNudge')->count());
    }

    public function test_personalization_excludes_blocked(): void
    {
        $me = User::factory()->create(['city' => 'Jakarta']);
        $blocked = User::factory()->create(['city' => 'Jakarta']);
        Block::create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);

        $rec = app(PersonalizationService::class)->getRecommendedMembers($me->fresh(), 10);
        $this->assertNotContains($blocked->id, $rec['ids'] ?? array_column($rec['picks'], 'user_id'));
    }

    public function test_profile_tips_from_real_gaps(): void
    {
        $me = User::factory()->create();
        $out = $this->actingAs($me)->getJson('/api/v1/ai/profile-tips')->assertOk()->json();
        $this->assertArrayHasKey('tips', $out);
        $this->assertArrayHasKey('score', $out);
        $this->assertNotEmpty($out['tips']);
    }

    public function test_courtship_digest_requires_party(): void
    {
        [$a, $b] = [$this->matchedPair()[0], $this->matchedPair()[1]];
        $courtship = Courtship::create([
            'initiator_id' => $a->id, 'partner_id' => $b->id, 'stage' => 'kenalan', 'status' => 'active',
        ]);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson("/biro-jodoh/taaruf/{$courtship->id}/ringkasan")->assertForbidden();
    }
}
