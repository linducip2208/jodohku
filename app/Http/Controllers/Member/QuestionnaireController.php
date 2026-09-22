<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerQuestionnaireRequest;
use App\Jobs\RecalculateMatches;
use App\Models\Question;
use App\Models\QuestionnaireAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionnaireController extends Controller
{
    public function index(Request $request)
    {
        $questions = Question::where('is_active', true)->with('options')->orderBy('sort_order')->paginate(50);
        $answers = QuestionnaireAnswer::where('user_id', $request->user()->id)->get()->keyBy('question_id');

        if ($request->wantsJson()) {
            return response()->json(['questions' => $questions, 'answers' => $answers->values()]);
        }

        return view('member.questionnaire', ['questions' => $questions]);
    }

    public function store(AnswerQuestionnaireRequest $request)
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
