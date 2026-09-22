<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationLog;
use App\Models\ModerationQueue;
use App\Models\ProfanityWord;
use App\Models\Report;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ModerationController extends Controller
{
    public function queues(Request $request)
    {
        $items = ModerationQueue::latest('id')->paginate(25);

        return $request->wantsJson() ? response()->json($items) : view('admin.moderation', ['items' => $items]);
    }

    public function decide(Request $request, ModerationQueue $queue, AuditService $audit)
    {
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

    public function resolveReport(Request $request, Report $report, AuditService $audit)
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
        $words = ProfanityWord::with('category')->latest('id')->paginate(50);

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
            'severity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_regex' => ['sometimes', 'boolean'],
            'profanity_category_id' => ['nullable', 'integer', 'exists:profanity_categories,id'],
        ]);
        $word = ProfanityWord::create($data + ['is_active' => true]);
        $this->bustDictionaryCache();
        $audit->log('admin.profanity.created', $request->user(), $word);

        return $request->wantsJson() ? response()->json($word, 201) : back()->with('status', 'Kata ditambahkan.');
    }

    public function updateWord(Request $request, ProfanityWord $word, AuditService $audit)
    {
        $before = $word->only(['word', 'replacement', 'is_active']);
        $data = $request->validate([
            'word' => ['sometimes', 'string', 'max:120', 'unique:profanity_words,word,'.$word->id],
            'replacement' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_regex' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'profanity_category_id' => ['nullable', 'integer', 'exists:profanity_categories,id'],
        ]);
        // Validate regex before saving to avoid ReDoS / broken patterns.
        if (array_key_exists('is_regex', $data) || array_key_exists('word', $data)) {
            $isRegex = (bool) ($data['is_regex'] ?? $word->is_regex);
            $pattern = (string) ($data['word'] ?? $word->word);
            if ($isRegex) {
                $ok = @preg_match($pattern, '');
                if ($ok === false) {
                    return response()->json(['message' => 'Invalid regex pattern.'], 422);
                }
                // Reject catastrophic patterns: nested quantifiers.
                if (preg_match('/(\+|\*|\{[^}]+\})(\+|\*|\?)?[^\/]*(\+|\*|\{)/', $pattern) && strlen($pattern) > 60) {
                    return response()->json(['message' => 'Regex too complex (ReDoS risk).'], 422);
                }
            }
        }
        $word->update($data);
        $this->bustDictionaryCache();
        $audit->log('admin.profanity.updated', $request->user(), $word, $before, []);

        return $request->wantsJson() ? response()->json($word->fresh()) : back()->with('status', 'Kata diperbarui.');
    }

    /** Dry-run: test a sentence against the dictionary before saving. */
    public function testWord(Request $request, \App\Services\ProfanityService $profanity, \App\Services\ScamDetectionService $scam)
    {
        $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $text = (string) $request->input('text');
        $censor = $profanity->censor($text);
        $normalized = $profanity->normalize($text);

        return response()->json([
            'clean' => $censor['clean'],
            'hits' => $censor['hits'],
            'obfuscated' => $censor['obfuscated'],
            'categories' => $censor['categories'],
            'normalized' => $normalized,
            'scam' => $scam->analyze($text, $normalized),
        ]);
    }

    public function destroyWord(Request $request, ProfanityWord $word, AuditService $audit)
    {
        $word->delete();
        $this->bustDictionaryCache();
        $audit->log('admin.profanity.deleted', $request->user(), $word);

        return $request->wantsJson() ? response()->json(['message' => 'Deleted.']) : back()->with('status', 'Kata dihapus.');
    }

    protected function bustDictionaryCache(): void
    {
        Cache::forget('profanity_words:v3');
        Cache::forget('profanity_words:v2');
        Cache::forget('profanity_words');
    }
}
