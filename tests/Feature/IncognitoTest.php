<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\ProfilePrivacy;
use App\Models\User;
use App\Services\DiscoveryService;
use App\Services\LikeService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncognitoTest extends TestCase
{
    use RefreshDatabase;

    public function test_incognito_hidden_until_they_like_viewer(): void
    {
        $viewer = User::factory()->create(['city' => 'Jakarta']);
        $ghost = User::factory()->create(['city' => 'Jakarta']);
        ProfilePrivacy::firstOrCreate(['user_id' => $ghost->id], ['is_incognito' => true]);
        $ghost->profilePrivacy()->update(['is_incognito' => true]);

        /** @var DiscoveryService $disco */
        $disco = app(DiscoveryService::class);
        $ids = $disco->discover($viewer, [], 50)->pluck('id')->all();
        $this->assertNotContains($ghost->id, $ids);

        // After ghost likes viewer, ghost becomes visible to viewer.
        app(LikeService::class)->like($ghost, $viewer);
        $ids2 = app(DiscoveryService::class)->discover($viewer, [], 50)->pluck('id')->all();
        $this->assertContains($ghost->id, $ids2);

        // Engine-level candidates honor the same rule.
        $cands = app(MatchingEngine::class)->candidatesFor($viewer, [], 50)->pluck('id')->all();
        $this->assertContains($ghost->id, $cands);
    }

    public function test_privacy_settings_persist(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/settings/privacy', [
            'hide_online' => '1', 'incognito' => '1', 'hide_distance' => '1',
        ])->assertRedirect();
        $p = ProfilePrivacy::where('user_id', $u->id)->firstOrFail();
        $this->assertFalse((bool) $p->show_online_status);
        $this->assertTrue((bool) $p->is_incognito);
        $this->assertFalse((bool) $p->show_distance);

        $this->actingAs($u)->post('/settings/notifications', ['match' => '1'])
            ->assertRedirect();
        $np = NotificationPreference::where('user_id', $u->id)->firstOrFail();
        $this->assertTrue((bool) $np->email_matches);
        $this->assertFalse((bool) $np->email_messages);
    }
}
