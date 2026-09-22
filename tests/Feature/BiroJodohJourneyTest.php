<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Counselor;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuestionnaireVersion;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiroJodohJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function register(string $name, string $email): array
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => $name, 'email' => $email,
            'password' => 'password123', 'password_confirmation' => 'password123',
            'city' => 'Jakarta',
        ])->assertCreated();

        return [$res->json('user.id'), $res->json('token')];
    }

    protected function asToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_full_biro_jodoh_journey(): void
    {
        config()->set('jodohku.features.ai', false);

        // ---- REGISTER + PROFILE + PREFERENCE ----
        [$aId, $aToken] = $this->register('User A', 'a.journey@example.test');
        [$bId, $bToken] = $this->register('User B', 'b.journey@example.test');
        $this->asToken($aToken)->putJson('/api/v1/profile', [
            'display_name' => 'Ayu', 'bio' => 'Suka masak.',
            'relationship_goal' => 'marriage',
        ])->assertOk();
        $this->asToken($bToken)->putJson('/api/v1/profile', [
            'display_name' => 'Bimo', 'bio' => 'Suka hiking.',
            'relationship_goal' => 'marriage',
        ])->assertOk();
        $this->asToken($aToken)->putJson('/api/v1/preferences', ['min_age' => 20, 'max_age' => 35])->assertOk();

        // ---- QUESTIONNAIRE (different answers → discuss topics later) ----
        $version = QuestionnaireVersion::create(['version' => 1, 'title' => 'v1', 'is_active' => true]);
        $cat = QuestionCategory::create(['key' => 'values', 'slug' => 'values', 'name' => 'Values', 'severity' => 3]);
        $q = Question::create([
            'question_category_id' => $cat->id, 'questionnaire_version_id' => $version->id,
            'category_key' => 'values', 'question_text' => 'Tinggal dengan orang tua setelah menikah?',
            'is_active' => true,
        ]);
        $o1 = QuestionOption::create(['question_id' => $q->id, 'option_text' => 'Ya', 'option_value' => 'yes']);
        $o2 = QuestionOption::create(['question_id' => $q->id, 'option_text' => 'Tidak', 'option_value' => 'no']);
        $this->asToken($aToken)->postJson('/api/v1/questionnaire/answers', [
            'questionnaire_version_id' => $version->id,
            'answers' => [['question_id' => $q->id, 'question_option_id' => $o1->id]],
        ])->assertCreated();
        $this->asToken($bToken)->postJson('/api/v1/questionnaire/answers', [
            'questionnaire_version_id' => $version->id,
            'answers' => [['question_id' => $q->id, 'question_option_id' => $o2->id]],
        ])->assertCreated();

        // ---- DISCOVERY → LIKE → MUTUAL MATCH ----
        $this->asToken($aToken)->postJson("/api/v1/likes/{$bId}")->assertCreated();
        $this->asToken($bToken)->postJson("/api/v1/likes/{$aId}")->assertCreated()->assertJsonPath('is_new_match', true);

        // ---- COMPATIBILITY REPORT (grounded why + discuss) ----
        $report = $this->asToken($aToken)->postJson('/api/v1/compatibility-reports', ['candidate_id' => $bId])
            ->assertCreated();
        $this->assertStringContainsString('Tujuan hubungan sama', implode(' ', $report->json('breakdown.why')));
        $this->assertNotEmpty($report->json('breakdown.discuss'));

        // ---- CHAT + AI TOPICS + SAFETY ----
        $convId = $this->asToken($aToken)->postJson('/api/v1/conversations', ['user_id' => $bId])
            ->assertCreated()->json('conversation_id');
        $this->asToken($aToken)->postJson("/api/v1/conversations/{$convId}/messages", ['body' => 'Assalamualaikum, salam kenal'])->assertCreated();
        $this->asToken($bToken)->postJson("/api/v1/conversations/{$convId}/messages", ['body' => 'Waalaikumsalam, salam kenal juga'])->assertCreated();
        $topics = $this->asToken($aToken)->getJson("/api/v1/ai/taaruf-topics/{$bId}")->assertOk()->json('topics');
        $this->assertNotEmpty($topics);
        $this->asToken($aToken)->getJson("/api/v1/chat/{$convId}/safety")->assertOk()->assertJsonPath('warning', false);

        // ---- COURTSHIP: start → taaruf → guardian → khitbah ----
        $courtId = $this->asToken($aToken)->postJson('/api/v1/courtships', ['partner_id' => $bId])->assertCreated()->json('id');
        $this->asToken($aToken)->postJson("/api/v1/courtships/{$courtId}/advance")->assertOk()->assertJsonPath('stage', 'taaruf');
        $this->asToken($aToken)->putJson("/api/v1/courtships/{$courtId}/guardian", [
            'guardian_name' => 'H. Ahmad', 'guardian_relation' => 'Ayah',
        ])->assertOk();
        $this->asToken($bToken)->postJson("/api/v1/courtships/{$courtId}/guardian/approve")->assertOk();
        $this->asToken($aToken)->postJson("/api/v1/courtships/{$courtId}/advance")->assertOk()->assertJsonPath('stage', 'khitbah');

        // ---- COUNSELOR: booking with shared report → confirm → complete ----
        $staff = User::factory()->create();
        $counselor = Counselor::create(['user_id' => $staff->id, 'specialty' => 'Pranikah', 'is_active' => true]);
        $bookId = $this->asToken($aToken)->postJson('/api/v1/consultations', [
            'counselor_id' => $counselor->id, 'topic' => 'Kesiapan menikah',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'share_report' => true, 'shared_report_id' => $report->json('id'),
        ])->assertCreated()->json('id');
        $this->asToken($aToken)->postJson("/api/v1/consultations/{$bookId}/confirm")->assertForbidden();
        $this->actingAs($staff)->postJson("/api/v1/consultations/{$bookId}/confirm")->assertOk();
        $this->actingAs($staff)->postJson("/api/v1/consultations/{$bookId}/complete")->assertOk();

        // ---- JOURNEY reflects everything ----
        $journey = $this->asToken($aToken)->getJson("/api/v1/courtships/journey/{$bId}")->assertOk()->json();
        $steps = collect($journey['steps'])->keyBy('key');
        foreach (['match', 'chat', 'perkenalan', 'taaruf', 'konseling', 'keluarga'] as $key) {
            $this->assertTrue($steps[$key]['done'], "Step {$key} should be done.");
        }
        $this->assertFalse($steps['menikah']['done']);

        // ---- MARRIAGE + SUCCESS STORY ----
        $this->asToken($aToken)->postJson("/api/v1/courtships/{$courtId}/advance")->assertOk()->assertJsonPath('status', 'completed');
        $story = 'Kami bertemu di Jodohku, menjalani taaruf yang baik didampingi wali dan konselor, lalu menikah dengan bahagia dan penuh berkah selalu.';
        $storyId = $this->asToken($aToken)->postJson('/api/v1/success-stories', ['partner_name' => 'Bimo', 'story' => $story])->assertCreated()->json('id');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->postJson("/admin/biro-jodoh/stories/{$storyId}/moderate", ['action' => 'publish'])->assertOk();
        $this->asToken($bToken)->getJson('/api/v1/success-stories')->assertOk()->assertJsonCount(1, 'data');

        // ---- Member pages render ----
        $this->actingAs(User::find($aId))->get('/biro-jodoh/taaruf')->assertOk()->assertSee('Perjalanan Taaruf');
        $this->actingAs(User::find($aId))->get("/biro-jodoh/taaruf/{$courtId}")->assertOk()->assertSee('Menikah');
        $this->actingAs(User::find($aId))->get('/biro-jodoh/kisah')->assertOk();
    }
}
