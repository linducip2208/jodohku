<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireVersion;
use App\Models\User;
use App\Services\AiMatchmakerService;
use App\Services\MatchingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingDepthTest extends TestCase
{
    use RefreshDatabase;

    public function test_score_deterministic_and_explain_uses_real_data(): void
    {
        $a = User::factory()->create(['city' => 'Jakarta', 'date_of_birth' => now()->subYears(28)->toDateString()]);
        $b = User::factory()->create(['city' => 'Jakarta', 'date_of_birth' => now()->subYears(27)->toDateString()]);

        /** @var MatchingEngine $engine */
        $engine = app(MatchingEngine::class);
        $first = $engine->scorePair($a, $b);
        $second = $engine->scorePair($a->fresh(), $b->fresh());
        $this->assertEquals($first['mutual'], $second['mutual']);
        $this->assertEquals($first['a_to_b'], $second['a_to_b']);

        $explain = $engine->explain($a, $b);
        $this->assertArrayHasKey('common', $explain);
        $this->assertArrayHasKey('differences', $explain);
        $this->assertArrayHasKey('breakdown', $explain);
        // Every commonality string must reference real data, never placeholders.
        foreach (array_merge($explain['common'], $explain['differences']) as $line) {
            $this->assertDoesNotMatchRegularExpression('/lorem|todo|xxx|fake/i', (string) $line);
        }
    }

    public function test_questionnaire_versioning_preserves_history(): void
    {
        $v1 = QuestionnaireVersion::firstOrCreate(['version' => 9001], ['is_active' => true, 'notes' => 'test']);
        $q = Question::create([
            'question_category_id' => \App\Models\QuestionCategory::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test']) ->id,
            'questionnaire_version_id' => $v1->id,
            'category_key' => 'personality', 'type' => 'single_choice',
            'question_text' => 'Versi test?', 'sort_order' => 1, 'is_active' => true,
        ]);
        $user = User::factory()->create();
        QuestionnaireAnswer::create([
            'user_id' => $user->id, 'question_id' => $q->id,
            'questionnaire_version_id' => $v1->id, 'answer_text' => 'Jawaban v1',
        ]);

        // New version deactivates old; old answers stay queryable under v1.
        $v2 = QuestionnaireVersion::create(['version' => 9002, 'is_active' => true, 'notes' => 'test2']);
        $v1->update(['is_active' => false]);
        $this->assertEquals(1, QuestionnaireAnswer::where('user_id', $user->id)->where('questionnaire_version_id', $v1->id)->count());
        $this->assertEquals('Jawaban v1', QuestionnaireAnswer::where('user_id', $user->id)->first()->answer_text);
        $this->assertTrue(QuestionnaireVersion::where('version', 9002)->first()->is_active);
    }

    public function test_ai_matchmaker_only_recommends_real_profiles(): void
    {
        $me = User::factory()->create(['city' => 'Jakarta']);
        $real = User::factory()->create(['city' => 'Jakarta']);

        /** @var AiMatchmakerService $mm */
        $mm = app(AiMatchmakerService::class);
        $result = $mm->recommend($me, 'cari yang serius', 5);

        $this->assertArrayHasKey('picks', $result);
        $this->assertArrayHasKey('explanation', $result);
        $realIds = User::pluck('id')->all();
        foreach ($result['picks'] as $pick) {
            $this->assertContains($pick['user_id'], $realIds);
            $this->assertEquals(User::find($pick['user_id'])->displayName(), $pick['display_name']);
            $this->assertEquals(User::find($pick['user_id'])->age(), $pick['age']);
        }
        // Works with no AI key configured (fallback explanation, no exception).
        $this->assertNotEmpty($result['explanation']);
        $this->assertStringNotContainsStringIgnoringCase('lorem', $result['explanation']);
    }
}
