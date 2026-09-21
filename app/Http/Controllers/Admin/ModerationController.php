<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationAction;
use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\ModerationQueue;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModerationController extends Controller
{
    public function queues(Request $request)
    {
        $items = ModerationQueue::latest('id')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.moderation', ['items' => $items]);
    }

    public function decide(Request $request, ModerationQueue $queue, AuditService $audit)    {
        $request->validate([
            'action' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $queue, $audit) {
            $queue->update(['status' => 'resolved', 'handled_by' => $request->user()->id, 'resolved_at' => now()]);
            ModerationLog::create([
                'moderation_queue_id' => $queue->id,
                'moderator_id' => $request->user()->id,
                'action' => $request->input('action'),
                'notes' => $request->input('notes'),
            ]);
            $audit->log('admin.moderation.decided', $request->user(), $queue, [], ['action' => $request->input('action')]);
        });

        return response()->json(['message' => 'Decision recorded.']);
    }

    public function resolveReport(Request $request, \App\Models\Report $report, AuditService $audit)
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $report, $audit) {
            $report->resolve($request->user(), (string) $request->input('notes', ''));
            $audit->log('admin.report.resolved', $request->user(), $report, ['status' => 'pending'], ['status' => $report->status->value ?? $report->status]);
        });

        return $request->wantsJson()
            ? response()->json($report->fresh())
            : back()->with('status', 'Laporan diselesaikan.');
    }

    public function words(Request $request)
    {
        $words = \App\Models\ProfanityWord::with('category')->latest('id')->paginate(50);

        return $request->wantsJson()
            ? response()->json($words)
            : view('admin.moderation.words', ['words' => $words]);
    }

    public function storeWord(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:120', 'unique:profanity_words,word'],
            'replacement' => ['nullable', 'string', 'max:120'],
            'language' => ['required', 'string', 'in:id,en'],
            'severity' => ['nullable', 'string', 'in:low,medium,high'],
            'is_regex' => ['sometimes', 'boolean'],
            'profanity_category_id' => ['nullable', 'integer', 'exists:profanity_categories,id'],
        ]);
        $word = \App\Models\ProfanityWord::create($data + ['is_active' => true]);
        $this->bustDictionaryCache();
        $audit->log('admin.profanity.created', $request->user(), $word);

        return $request->wantsJson() ? response()->json($word, 201) : back()->with('status', 'Kata ditambahkan.');
    }

    public function updateWord(Request $request, \App\Models\ProfanityWord $word, AuditService $audit)
    {
        $before = $word->only(['word', 'replacement', 'is_active']);
        $word->update($request->validate([
            'replacement' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', 'string', 'in:low,medium,high'],
            'is_regex' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'profanity_category_id' => ['nullable', 'integer', 'exists:profanity_categories,id'],
        ]));
        $this->bustDictionaryCache();
        $audit->log('admin.profanity.updated', $request->user(), $word, $before, []);

        return $request->wantsJson() ? response()->json($word->fresh()) : back()->with('status', 'Kata diperbarui.');
    }

    public function destroyWord(Request $request, \App\Models\ProfanityWord $word, AuditService $audit)
    {
        $word->delete();
        $this->bustDictionaryCache();
        $audit->log('admin.profanity.deleted', $request->user(), $word);

        return $request->wantsJson() ? response()->json(['message' => 'Deleted.']) : back()->with('status', 'Kata dihapus.');
    }

    protected function bustDictionaryCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('profanity_words:v2');
        \Illuminate\Support\Facades\Cache::forget('profanity_words');
    }
}
