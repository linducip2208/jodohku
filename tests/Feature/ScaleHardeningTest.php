<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\UserRegistered;
use App\Jobs\PruneStaleData;
use App\Jobs\RecalculateMatches;
use App\Jobs\RecordProfileView;
use App\Models\AuditLog;
use App\Models\MatchScore;
use App\Models\ModerationQueue;
use App\Models\ProfileView;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiService;
use App\Services\DiscoveryService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Tests\TestCase;

class ScaleHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_jobs_define_tries_timeout_backoff(): void
    {
        foreach (glob(app_path('Jobs/*.php')) as $file) {
            $class = 'App\\Jobs\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $job = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $this->assertTrue(isset($job->tries) && $job->tries >= 2, $class.' missing $tries');
            $this->assertTrue(isset($job->timeout) && $job->timeout >= 60, $class.' missing $timeout');
            $this->assertIsArray($job->backoff(), $class.' missing backoff()');
        }
    }

    public function test_ai_kill_switch_blocks_chat(): void
    {
        config(['ai.spending.kill_switch' => true]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/disabled/i');
        app(AiService::class)->chat('halo', [], null, 'chat');
    }

    public function test_ai_monthly_cap_blocks_when_exceeded(): void
    {
        config(['ai.spending.kill_switch' => false, 'ai.spending.monthly_cap_usd' => 0.000001]);
        Cache::forget('ai:spend:'.now()->format('Ym'));
        DB::table('ai_usage_logs')->insert([
            'user_id' => null, 'purpose' => 'chat', 'input_tokens' => 100000,
            'output_tokens' => 100000, 'cost' => 99.0, 'is_success' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/budget/i');
        app(AiService::class)->chat('halo', [], null, 'chat');
    }

    public function test_record_profile_view_throttled_to_once_per_day(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        (new RecordProfileView($owner->id, $viewer->id))->handle();
        (new RecordProfileView($owner->id, $viewer->id))->handle();
        $this->assertEquals(1, ProfileView::where('profile_user_id', $owner->id)->count());

        // Self-views never recorded.
        (new RecordProfileView($owner->id, $owner->id))->handle();
        $this->assertEquals(1, ProfileView::count());

        // Owner opt-out respected.
        $private = User::factory()->create();
        $private->profilePrivacy()->updateOrCreate([], ['allow_profile_views' => false]);
        (new RecordProfileView($private->id, $viewer->id))->handle();
        $this->assertEquals(0, ProfileView::where('profile_user_id', $private->id)->count());
    }

    public function test_register_warms_match_scores_async(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        event(new UserRegistered($user));
        Queue::assertPushed(RecalculateMatches::class, fn ($j) => $j->userId === $user->id);
    }

    public function test_prune_stale_data_deletes_old_keeps_fresh(): void
    {
        $user = User::factory()->create();
        $oldId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $oldId, 'type' => 'x', 'notifiable_type' => User::class,
            'notifiable_id' => $user->id, 'data' => '{}',
            'read_at' => now()->subDays(100), 'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100),
        ]);
        $freshId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $freshId, 'type' => 'x', 'notifiable_type' => User::class,
            'notifiable_id' => $user->id, 'data' => '{}',
            'read_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLog::create(['actor_id' => $user->id, 'action' => 'fresh']);
        DB::table('audit_logs')->insert(['actor_id' => $user->id, 'action' => 'old', 'created_at' => now()->subDays(200), 'updated_at' => now()->subDays(200)]);
        $other = User::factory()->create();
        [$c1, $c2] = MatchingEngine::canonical($user->id, $other->id);
        // Observer warming may already have scored this pair (sync queue in
        // tests) — upsert the stale row instead of assuming a clean table.
        MatchScore::updateOrCreate(['user_id' => $c1, 'candidate_id' => $c2], ['total_score' => 10, 'computed_at' => now()->subDays(100)]);

        (new PruneStaleData)->handle();

        $this->assertDatabaseMissing('notifications', ['id' => $oldId]);
        $this->assertDatabaseHas('notifications', ['id' => $freshId]);
        $this->assertEquals(0, AuditLog::where('action', 'old')->count());
        $this->assertEquals(0, MatchScore::where('computed_at', '<', now()->subDays(90))->count());
    }

    public function test_prune_honors_admin_retention_settings(): void
    {
        // set() refreshes the static cache (updateOrCreate alone would not).
        Setting::set('notifications_read_days', 1000, 'retention', 'integer');
        $user = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id, 'type' => 'x', 'notifiable_type' => User::class,
            'notifiable_id' => $user->id, 'data' => '{}',
            'read_at' => now()->subDays(100), 'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100),
        ]);
        (new PruneStaleData)->handle();
        // 100 days < custom 1000-day retention: survives.
        $this->assertDatabaseHas('notifications', ['id' => $id]);
    }

    public function test_analytics_endpoints_are_cached(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Cache::flush();
        $this->actingAs($admin)->getJson('/admin/analytics/kpi')->assertOk();
        $this->assertNotNull(Cache::get('admin.analytics.kpi:7'));
        $this->actingAs($admin)->getJson('/admin/analytics/funnel')->assertOk();
        $this->assertNotNull(Cache::get('admin.analytics.funnel'));
    }

    public function test_discovery_backfills_wider_radius_on_thin_pool(): void
    {
        $me = User::factory()->create(['latitude' => -6.2, 'longitude' => 106.8, 'city' => 'Jakarta']);
        $near = User::factory()->create(['latitude' => -6.21, 'longitude' => 106.81, 'city' => 'Jakarta']);
        // Bandung ≈120km: outside 50km bbox, inside 150km backfill cap.
        $far = User::factory()->create(['latitude' => -6.9, 'longitude' => 107.6, 'city' => 'Bandung']);

        $ids = app(DiscoveryService::class)->discover($me, ['max_distance_km' => 50], 10)->pluck('id')->all();

        $this->assertContains($near->id, $ids);
        $this->assertContains($far->id, $ids);
    }

    public function test_moderation_bulk_decide_approves_batch(): void
    {
        $mod = User::factory()->create(['role' => UserRole::Moderator]);
        $reporter = User::factory()->create();
        $mk = fn () => ModerationQueue::create([
            'queueable_type' => User::class, 'queueable_id' => $reporter->id,
            'reported_by' => $reporter->id, 'reason' => 'spam', 'status' => 'pending',
        ]);
        $a = $mk();
        $b = $mk();

        $this->actingAs($mod)->postJson('/admin/moderation/bulk-decide', [
            'queue_ids' => [$a->id, $b->id], 'action' => 'approved',
        ])->assertOk()->assertJsonPath('processed', 2);

        $this->assertEquals('approved', $a->fresh()->status);
        $this->assertEquals('approved', $b->fresh()->status);
        $this->assertEquals($mod->id, $a->fresh()->reviewer_id);
        $this->assertNotNull($a->fresh()->reviewed_at);
    }

    public function test_moderation_bulk_decide_validates(): void
    {
        $mod = User::factory()->create(['role' => UserRole::Moderator]);
        $this->actingAs($mod)->postJson('/admin/moderation/bulk-decide', [
            'queue_ids' => [], 'action' => 'approved',
        ])->assertStatus(422);
        $this->actingAs($mod)->postJson('/admin/moderation/bulk-decide', [
            'queue_ids' => [999999], 'action' => 'approved',
        ])->assertStatus(422);
        $item = ModerationQueue::create([
            'queueable_type' => User::class, 'queueable_id' => $mod->id, 'status' => 'pending',
        ]);
        $this->actingAs($mod)->postJson('/admin/moderation/bulk-decide', [
            'queue_ids' => [$item->id], 'action' => 'nuke',
        ])->assertStatus(422);
        $this->assertEquals('pending', $item->fresh()->status);
    }

    public function test_list_skeletons_render_while_loading(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/discover')->assertOk()->assertSee('jk-skeleton', false);
        $this->actingAs($user)->get('/matches')->assertOk()->assertSee('jk-skeleton', false);
        $this->actingAs($user)->get('/chat')->assertOk()->assertSee('jk-skeleton', false);
    }

    public function test_scout_default_is_noop_and_index_is_public_only(): void
    {
        // SCOUT_DRIVER=null env string converts to PHP null; Scout maps that
        // to NullEngine (verified in EngineManager::getDefaultDriver).
        $engine = app(EngineManager::class)->engine();
        $this->assertInstanceOf(NullEngine::class, $engine);

        $user = User::factory()->create();
        $doc = $user->toSearchableArray();
        $this->assertEquals(['id', 'display_name', 'username', 'city', 'province', 'gender'], array_keys($doc));
        foreach (['email', 'phone', 'password', 'latitude', 'longitude', 'date_of_birth'] as $secret) {
            $this->assertArrayNotHasKey($secret, $doc);
        }

        // Null driver: search() never touches MySQL, returns empty.
        $this->assertCount(0, User::search('jakarta')->get());

        // shouldBeSearchable mirrors retrieval privacy.
        $this->assertTrue($user->shouldBeSearchable());
        $ghost = User::factory()->create();
        $ghost->profilePrivacy()->updateOrCreate([], ['is_incognito' => true]);
        $this->assertFalse($ghost->fresh()->shouldBeSearchable());
        $banned = User::factory()->create(['status' => 'banned']);
        $this->assertFalse($banned->shouldBeSearchable());
        $deleted = User::factory()->create();
        $deleted->delete();
        $this->assertFalse($deleted->shouldBeSearchable());
    }
}
