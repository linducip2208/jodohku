<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionStoreRequest;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuestionnaireVersion;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchingAdminController extends Controller
{
    public function questions(Request $request)
    {
        return response()->json(Question::with(['options', 'category', 'version'])->orderBy('sort_order')->paginate(50));
    }

    public function storeQuestion(QuestionStoreRequest $request, AuditService $audit)
    {
        $question = DB::transaction(function () use ($request) {
            $q = Question::create($request->only([
                'question_category_id', 'questionnaire_version_id', 'category_key', 'type',
                'question_text', 'help_text', 'sort_order', 'weight', 'is_required', 'is_active',
            ]));
            foreach ((array) $request->input('options', []) as $i => $opt) {
                $q->options()->create([
                    'option_text' => $opt['option_text'],
                    'option_value' => $opt['option_value'] ?? null,
                    'score' => $opt['score'] ?? 0,
                    'sort_order' => $opt['sort_order'] ?? $i,
                ]);
            }

            return $q->fresh('options');
        });
        $audit->log('admin.question.created', $request->user(), $question);

        return response()->json($question, 201);
    }

    public function updateQuestion(Request $request, Question $question, AuditService $audit)
    {
        $question->update($request->only([
            'question_text', 'help_text', 'sort_order', 'weight', 'is_required', 'is_active', 'type', 'category_key',
        ]));
        $audit->log('admin.question.updated', $request->user(), $question);

        return response()->json($question->fresh());
    }

    public function destroyQuestion(Request $request, Question $question, AuditService $audit)
    {
        $question->delete();
        $audit->log('admin.question.deleted', $request->user(), $question);

        return response()->json(['message' => 'Deleted.']);
    }

    public function categories(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate(['name' => ['required', 'string', 'max:160'], 'key' => ['nullable', 'string', 'max:80']]);
            $cat = QuestionCategory::create($request->only(['name', 'key', 'description']));

            return response()->json($cat, 201);
        }

        return response()->json(QuestionCategory::orderBy('id')->get());
    }

    public function versions(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate(['version' => ['required', 'string', 'max:40'], 'is_active' => ['nullable', 'boolean']]);
            $v = QuestionnaireVersion::create($request->only(['version', 'is_active', 'notes']));

            return response()->json($v, 201);
        }

        return response()->json(QuestionnaireVersion::orderByDesc('id')->get());
    }

    public function weights(Request $request)
    {
        if ($request->isMethod('post') || $request->isMethod('put')) {
            $request->validate(['weights' => ['required', 'array']]);
            foreach ((array) $request->input('weights') as $key => $value) {
                \App\Models\Setting::updateOrCreate(['key' => 'match.weight.'.$key], ['value' => (string) $value, 'group' => 'matchmaking']);
            }

            return response()->json(['weights' => $request->input('weights')]);
        }

        return response()->json(config('matchmaking.weights'));
    }

    public function demographic(Request $request, \App\Services\MatchingEngine $engine)
    {
        return response()->json($engine->demographicBreakdown($request->user()));
    }

    public function syncWeights(Request $request, \App\Services\MatchingEngine $engine)
    {
        $request->validate(['weights' => ['required', 'array']]);
        $updated = $engine->updateWeights($request->input('weights'));

        return response()->json(['weights' => $updated]);
    }
}
