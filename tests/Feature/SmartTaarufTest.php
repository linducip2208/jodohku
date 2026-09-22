<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ChatWindow;
use App\Models\CompatibilityReport;
use App\Models\Counselor;
use App\Models\FraudRiskScore;
use App\Models\Interest;
use App\Models\ProfanityCategory;
use App\Models\ProfanityWord;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserMatch;
use App\Notifications\ConsultationStatusChanged;
use App\Notifications\CourtshipStageChanged;
use App\Services\ChatService;
use App\Services\MessageModerationService;
use App\Services\ProfanityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SmartTaarufTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::clearCache();
    }

    protected function matchedPair(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
        UserMatch::create(['user_a_id' => $u1, 'user_b_id' => $u2, 'is_active' => true, 'matched_at' => now()]);

        return [$a, $b];
    }

    public function test_admin_chat_settings_rbac_and_persistence(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $mod = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($admin)->get('/admin/chat/settings')->assertOk()->assertSee('Kuota per User');
        // Chat settings are admin-only by design (sensitive thresholds + quotas).
        $this->actingAs($mod)->get('/admin/chat/settings')->assertForbidden();

        $this->actingAs($mod)->postJson('/admin/settings', ['settings' => ['chat.free_messages_per_peer' => 5]])->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/settings', ['settings' => ['chat.free_messages_per_peer' => 5]])->assertForbidden();
        // Settings live in the same table as secrets: updates are superadmin-only.
        $super = User::factory()->create(['role' => UserRole::Superadmin]);
        $this->actingAs($super)->postJson('/admin/settings', ['settings' => ['chat.free_messages_per_peer' => 2]])
            ->assertOk()->assertJsonPath('message', 'Settings saved.');
        $this->assertEquals(2, Setting::get('free_messages_per_peer', 1, 'chat'));
        $this->assertEquals(2, app(ChatService::class)->messageLimitFor(User::factory()->create()));
    }

    public function test_profanity_normalization_and_false_positives(): void
    {
        $svc = app(ProfanityService::class);
        ProfanityCategory::firstOrCreate(['slug' => 'kasar-id'], ['name' => 'Kasar', 'severity' => 3]);
        ProfanityWord::create(['word' => 'bangsat', 'language' => 'id', 'severity' => 4, 'is_active' => true, 'profanity_category_id' => ProfanityCategory::where('slug', 'kasar-id')->first()->id]);

        $plain = $svc->censor('Dasar bangsat kamu!');
        $this->assertEquals(1, $plain['count']);
        $this->assertStringNotContainsString('bangsat', strtolower($plain['clean']));

        // Obfuscation detected (caps, punctuation, repeats, leet) without double count.
        $obf = $svc->censor('DASAR B4NGSATTT!!! kamu');
        $this->assertNotEmpty($obf['obfuscated']);
        $this->assertGreaterThanOrEqual(4, $obf['max_severity']);

        // Substring inside a normal word is NOT flagged.
        $fp = $svc->censor('Kebangsatan adalah topik sejarah');
        $this->assertEquals(0, $fp['count']);
        $this->assertEmpty($fp['obfuscated']);
    }

    public function test_moderation_thresholds_from_settings(): void
    {
        $svc = app(MessageModerationService::class);
        // char_spam heuristic gives risk 8 → allow under default thresholds.
        $r = $svc->moderate('Halo apa kabarrrrrrrrrr');
        $this->assertEquals('allow', $r['decision']);

        Setting::set('warn_risk', 5, 'moderation', 'integer');
        $this->assertEquals('warning', $svc->moderate('Halo apa kabarrrrrrrrrr')['decision']);

        Setting::set('auto_block_risk', 5, 'moderation', 'integer');
        $this->assertEquals('block', $svc->moderate('Halo apa kabarrrrrrrrrr')['decision']);

        Setting::set('auto_block_risk', 85, 'moderation', 'integer');
        Setting::set('ai_review_enabled', 0, 'moderation', 'boolean');
        $this->assertFalse($svc->needsAiReview(90, []));
    }

    public function test_journey_reflects_actual_data(): void
    {
        [$a, $b] = $this->matchedPair();
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);
        app(ChatService::class)->sendMessage($conv, $a, ['body' => 'Assalamualaikum, salam kenal']);

        $res = $this->actingAs($a)->getJson("/api/v1/courtships/journey/{$b->id}")->assertOk();
        $steps = collect($res->json('steps'))->keyBy('key');
        $this->assertTrue($steps['match']['done']);
        $this->assertTrue($steps['chat']['done']);
        $this->assertFalse($steps['perkenalan']['done']);
        $this->assertEquals('perkenalan', $res->json('next_step.key'));
        $this->assertFalse($res->json('counseling_suggested'));

        // Start courtship + advance to taaruf → perkenalan/taaruf done, counseling suggested.
        $cid = $this->actingAs($a)->postJson('/api/v1/courtships', ['partner_id' => $b->id])->assertCreated()->json('id');
        $this->actingAs($a)->postJson("/api/v1/courtships/{$cid}/advance")->assertOk();
        $res2 = $this->actingAs($a)->getJson("/api/v1/courtships/journey/{$b->id}")->assertOk();
        $steps2 = collect($res2->json('steps'))->keyBy('key');
        $this->assertTrue($steps2['taaruf']['done']);
        $this->assertTrue($res2->json('counseling_suggested'));
        $this->assertEquals('konseling', $res2->json('next_step.key'));

        // Outsider cannot view someone else's journey.
        $this->actingAs(User::factory()->create())->getJson("/api/v1/courtships/journey/{$b->id}")->assertForbidden();
    }

    public function test_taaruf_topics_grounded(): void
    {
        config()->set('jodohku.features.ai', false);
        [$a, $b] = $this->matchedPair();
        $a->profile()->create(['relationship_goal' => 'marriage', 'want_children' => true, 'occupation' => 'Guru']);
        $b->profile()->create(['relationship_goal' => 'marriage', 'want_children' => false, 'occupation' => 'Dokter']);
        $interest = Interest::create(['name' => 'Membaca', 'slug' => 'membaca', 'is_active' => true]);
        $a->interests()->attach($interest->id);
        $b->interests()->attach($interest->id);

        $res = $this->actingAs($a)->getJson("/api/v1/ai/taaruf-topics/{$b->id}")->assertOk();
        $topics = $res->json('topics');
        $this->assertNotEmpty($topics);
        $blob = json_encode($topics);
        $this->assertStringContainsString('Membaca', $blob);
        $this->assertStringContainsString('momongan', $blob);
        foreach ($topics as $t) {
            $this->assertArrayHasKey('question', $t);
            $this->assertArrayHasKey('why', $t);
        }
    }

    public function test_report_why_and_discuss_grounded(): void
    {
        [$a, $b] = $this->matchedPair();
        $a->update(['city' => 'Jakarta']);
        $b->update(['city' => 'Jakarta']);
        $a->profile()->create(['relationship_goal' => 'marriage', 'religion' => 'Islam']);
        $b->profile()->create(['relationship_goal' => 'marriage', 'religion' => 'Islam']);

        $res = $this->actingAs($a)->postJson('/api/v1/compatibility-reports', ['candidate_id' => $b->id])->assertCreated();
        $breakdown = $res->json('breakdown');
        $this->assertNotEmpty($breakdown['why']);
        $why = implode(' ', $breakdown['why']);
        $this->assertStringContainsString('Tujuan hubungan sama', $why);
        $this->assertStringContainsString('Jakarta', $why);
        $this->assertNotEmpty($breakdown['discuss']);
    }

    public function test_consultation_share_consent(): void
    {
        [$a, $b] = $this->matchedPair();
        $reportId = $this->actingAs($a)->postJson('/api/v1/compatibility-reports', ['candidate_id' => $b->id])->assertCreated()->json('id');
        $counselorUser = User::factory()->create();
        $counselor = Counselor::create(['user_id' => $counselorUser->id, 'specialty' => 'Pranikah', 'is_active' => true]);

        // Cannot share someone else's report.
        $other = User::factory()->create();
        $otherReport = CompatibilityReport::create(['user_id' => $other->id, 'candidate_id' => $a->id, 'score' => 10]);
        $payload = ['counselor_id' => $counselor->id, 'topic' => 'Siap nikah?', 'scheduled_at' => now()->addDay()->toDateTimeString(), 'share_report' => true, 'shared_report_id' => $otherReport->id];
        $this->actingAs($a)->postJson('/api/v1/consultations', $payload)->assertStatus(422);

        $payload['shared_report_id'] = $reportId;
        $cid = $this->actingAs($a)->postJson('/api/v1/consultations', $payload)->assertCreated()->json('id');

        // Counselor sees the shared report; member sees own bookings without it forced.
        $list = $this->actingAs($counselorUser)->getJson('/api/v1/consultations/counseling')->assertOk()->json('data');
        $this->assertEquals($reportId, $list[0]['shared_report']['id']);
        $this->actingAs($other)->getJson('/api/v1/consultations/counseling')->assertForbidden();
    }

    public function test_journey_notifications_sent(): void
    {
        Notification::fake();
        [$a, $b] = $this->matchedPair();

        $cid = $this->actingAs($a)->postJson('/api/v1/courtships', ['partner_id' => $b->id])->assertCreated()->json('id');
        $this->actingAs($a)->postJson("/api/v1/courtships/{$cid}/advance")->assertOk();
        Notification::assertSentTo($b, CourtshipStageChanged::class);

        $counselorUser = User::factory()->create();
        $counselor = Counselor::create(['user_id' => $counselorUser->id, 'specialty' => 'Pranikah', 'is_active' => true]);
        $bookId = $this->actingAs($a)->postJson('/api/v1/consultations', [
            'counselor_id' => $counselor->id, 'topic' => 'Kesiapan', 'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])->assertCreated()->json('id');
        Notification::assertSentTo($counselorUser, ConsultationStatusChanged::class);
        $this->actingAs($counselorUser)->postJson("/api/v1/consultations/{$bookId}/confirm")->assertOk();
        Notification::assertSentTo($a, ConsultationStatusChanged::class);
    }

    public function test_safety_center_and_chat_badges(): void
    {
        [$a, $b] = $this->matchedPair();
        $conv = app(ChatService::class)->findOrCreateDirect($a, $b);

        $this->actingAs($a)->get('/safety')->assertOk()->assertSee('Status Keamanan Akunmu');
        $this->actingAs($a)->getJson('/safety')->assertOk()->assertJsonStructure(['tips', 'status']);

        // Stage chip appears after courtship advances to taaruf.
        $cid = $this->actingAs($a)->postJson('/api/v1/courtships', ['partner_id' => $b->id])->assertCreated()->json('id');
        $this->actingAs($a)->postJson("/api/v1/courtships/{$cid}/advance")->assertOk();
        $html = Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])->html();
        $this->assertStringContainsString('Taaruf: Taaruf', $html);
        $this->assertStringNotContainsString('Perhatikan keamanan', $html);

        FraudRiskScore::create(['user_id' => $b->id, 'score' => 60, 'level' => 'medium', 'scored_at' => now()]);
        $html2 = Livewire::actingAs($a)->test(ChatWindow::class, ['conversationId' => $conv->id])->html();
        $this->assertStringContainsString('Perhatikan keamanan', $html2);
    }
}
