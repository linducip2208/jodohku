<?php

namespace Tests\Feature;

use App\Models\Courtship;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\ChatService;
use App\Services\LikeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_resolve(): void
    {
        foreach (['/', '/login', '/register', '/privacy', '/terms', '/guidelines', '/contact', '/robots.txt', '/sitemap.xml', '/biro-jodoh', '/taaruf'] as $uri) {
            $this->get($uri)->assertOk();
        }
        // Auth-gated member pages redirect guests to login (never 404/500).
        foreach (['/home', '/discover', '/matches', '/likes', '/chat', '/notifications', '/settings', '/komunitas', '/premium', '/safety'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_member_journey_no_dead_ends(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        [$u1, $u2] = UserMatch::canonical($me->id, $other->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()]);

        $pages = [
            '/home', '/discover', '/profile/'.$other->id, '/profile/'.$me->id,
            '/profile/edit', '/likes', '/matches', '/visitors', '/favorites',
            '/chat', '/notifications', '/settings', '/safety', '/premium',
            '/credits', '/gifts', '/boosts', '/events', '/blog', '/forums',
            '/komunitas', '/biro-jodoh/taaruf', '/biro-jodoh/konselor',
            '/biro-jodoh/konsultasi', '/biro-jodoh/laporan', '/biro-jodoh/kisah',
            '/verification', '/questionnaire',
        ];
        foreach ($pages as $uri) {
            $res = $this->actingAs($me)->get($uri);
            // 403 is legitimate for premium-gated pages (/visitors); 404/500 never are.
            $this->assertTrue(
                in_array($res->getStatusCode(), [200, 302, 403]),
                "{$uri} returned {$res->getStatusCode()}"
            );
        }
    }

    public function test_full_match_to_taaruf_flow(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        // Like both ways via service (mirrors UI buttons).
        $likes = app(LikeService::class);
        $likes->like($a->fresh(), $b->fresh());
        $likes->like($b->fresh(), $a->fresh());
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        $this->assertTrue((bool) UserMatch::where('user_a_id', $u1)->where('user_b_id', $u2)->where('is_active', true)->exists());

        // Chat creation + message as member A.
        $chat = app(ChatService::class);
        $conv = $chat->findOrCreateDirect($a->fresh(), $b->fresh());
        $this->actingAs($a)->get('/chat/'.$conv->id)->assertOk();

        // Start taaruf from the match.
        $this->actingAs($a)->post('/biro-jodoh/taaruf/mulai', ['partner_id' => $b->id])->assertRedirect();
        $courtship = Courtship::where('initiator_id', $a->id)->where('partner_id', $b->id)->firstOrFail();
        $this->actingAs($a)->get('/biro-jodoh/taaruf/'.$courtship->id)->assertOk();
    }

    public function test_admin_pages_resolve_for_staff(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['/admin', '/admin/users', '/admin/moderation', '/admin/settings', '/admin/audit'] as $uri) {
            $res = $this->actingAs($admin)->get($uri);
            $this->assertTrue(in_array($res->getStatusCode(), [200, 302]), "{$uri} returned {$res->getStatusCode()}");
        }
    }
}
