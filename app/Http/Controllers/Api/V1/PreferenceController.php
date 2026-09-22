<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerQuestionnaireRequest;
use App\Http\Requests\PartnerPreferenceRequest;
use App\Jobs\RecalculateMatches;
use App\Models\Question;
use App\Models\QuestionnaireAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreferenceController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->partnerPreference()->firstOrCreate([]));
    }

    public function update(PartnerPreferenceRequest $request)
    {
        $request->user()->partnerPreference()->updateOrCreate([], $request->validated());

        return response()->json($request->user()->partnerPreference()->first()->fresh());
    }

    public function questions(Request $request)
    {
        return response()->json(Question::where('is_active', true)->with('options')->orderBy('sort_order')->paginate(50));
    }

    public function answers(Request $request)
    {
        return response()->json(QuestionnaireAnswer::where('user_id', $request->user()->id)->with('question')->get());
    }

    public function answer(AnswerQuestionnaireRequest $request)
    {
        $user = $request->user();
        $saved = DB::transaction(function () use ($user, $request) {
            $out = [];
            foreach ($request->input('answers', []) as $row) {
                $question = Question::findOrFail($row['question_id']);
                $out[] = QuestionnaireAnswer::updateOrCreate(
                    ['user_id' => $user->id, 'question_id' => $question->id],
                    [
                        'questionnaire_version_id' => $request->input('questionnaire_version_id', $question->questionnaire_version_id),
                        'question_option_id' => $row['question_option_id'] ?? null,
                        'answer_text' => $row['answer_text'] ?? null,
                        'answer_value' => $row['answer_value'] ?? null,
                        'answer_score' => $row['answer_score'] ?? null,
                        'importance' => $row['importance'] ?? null,
                    ]
                );
            }

            return $out;
        });
        RecalculateMatches::dispatch($user->id);

        return response()->json($saved, 201);
    }
}
