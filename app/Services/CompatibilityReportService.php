<?php

namespace App\Services;

use App\Models\CompatibilityReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompatibilityReportService
{
    public function __construct(protected MatchingEngine $engine, protected AuditService $audit) {}

    /** Generate (or refresh) a shareable compatibility report grounded on MatchingEngine. */
    public function generate(User $user, User $candidate): CompatibilityReport
    {
        if ((int) $user->id === (int) $candidate->id) {
            throw new \InvalidArgumentException('Cannot generate a report for yourself.');
        }

        return DB::transaction(function () use ($user, $candidate) {
            $score = $this->engine->scorePair($user, $candidate);
            $explanation = $this->engine->explain($user, $candidate);
            $why = $this->groundedReasons($user, $candidate, $score['breakdown'] ?? []);
            $discuss = $this->thingsToDiscuss($user, $candidate);
            $report = CompatibilityReport::updateOrCreate(
                ['user_id' => $user->id, 'candidate_id' => $candidate->id],
                [
                    'score' => round((float) ($score['mutual'] ?? 0), 2),
                    'breakdown' => ['score' => $score, 'explanation' => $explanation, 'why' => $why, 'discuss' => $discuss],
                    'summary' => $this->summarize($user, $candidate, (float) ($score['mutual'] ?? 0), $score['breakdown'] ?? []),
                ]
            );
            $this->audit->log('compatibility_report.generated', $user, $report, [], ['candidate_id' => $candidate->id]);

            return $report->fresh();
        });
    }

    /** Human reasons derived strictly from real profile data — never invented. */
    protected function groundedReasons(User $user, User $candidate, array $breakdown): array
    {
        $user->loadMissing(['profile', 'interests']);
        $candidate->loadMissing(['profile', 'interests']);
        $reasons = [];

        $myGoal = $this->enumVal($user->profile?->relationship_goal);
        $theirGoal = $this->enumVal($candidate->profile?->relationship_goal);
        if ($myGoal && $myGoal === $theirGoal) {
            $reasons[] = 'Tujuan hubungan sama'.($myGoal ? ' ('.$myGoal.')' : '');
        }
        $shared = $user->interests->pluck('name')->intersect($candidate->interests->pluck('name'))->values();
        if ($shared->isNotEmpty()) {
            $reasons[] = 'Minat yang sama: '.$shared->take(3)->implode(', ');
        }
        if ($user->city && $candidate->city && strtolower($user->city) === strtolower($candidate->city)) {
            $reasons[] = 'Sama-sama tinggal di '.$candidate->city;
        }
        $myRel = $this->enumVal($user->profile?->religion);
        $theirRel = $this->enumVal($candidate->profile?->religion);
        if ($myRel && $myRel === $theirRel) {
            $reasons[] = 'Latar agama sama ('.$myRel.')';
        }
        foreach (['interest' => 'Minat', 'goal' => 'Tujuan', 'lifestyle' => 'Gaya hidup', 'personality' => 'Kepribadian'] as $dim => $label) {
            if (($breakdown[$dim] ?? 0) >= 80 && count($reasons) < 6) {
                $reasons[] = $label.' sangat selaras (skor '.round($breakdown[$dim]).')';
            }
        }
        if (empty($reasons)) {
            $reasons[] = 'Belum cukup data untuk menjelaskan bagian ini.';
        }

        return array_values(array_unique($reasons));
    }

    /** Sensitive-but-important topics derived from real differences. */
    protected function thingsToDiscuss(User $user, User $candidate): array
    {
        $user->loadMissing(['profile']);
        $candidate->loadMissing(['profile']);
        $items = [];

        $myGoal = $this->enumVal($user->profile?->relationship_goal);
        $theirGoal = $this->enumVal($candidate->profile?->relationship_goal);
        if ($myGoal && $theirGoal && $myGoal !== $theirGoal) {
            $items[] = 'Tujuan hubungan berbeda — bicarakan ekspektasi sejak awal taaruf.';
        }
        if ($user->city && $candidate->city && strtolower($user->city) !== strtolower($candidate->city)) {
            $items[] = 'Beda kota — diskusikan rencana tempat tinggal setelah menikah.';
        }
        $myKids = $user->profile?->want_children;
        $theirKids = $candidate->profile?->want_children;
        if ($myKids !== null && $theirKids !== null && (bool) $myKids !== (bool) $theirKids) {
            $items[] = 'Pandangan soal momongan berbeda — penting dibahas sebelum khitbah.';
        }
        $assistant = app(AiChatAssistantService::class);
        foreach ($assistant->questionnaireDiffs($user, $candidate, 3) as $diff) {
            $items[] = 'Perbedaan pandangan ('.$diff['category'].'): '.$diff['question'];
        }
        if (empty($items)) {
            $items[] = 'Belum cukup data untuk menyarankan topik — lengkapi kuesioner kalian.';
        }

        return array_slice(array_values(array_unique($items)), 0, 5);
    }

    protected function enumVal(mixed $v): ?string
    {
        if ($v instanceof \BackedEnum) {
            return $v->value;
        }

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    protected function summarize(User $user, User $candidate, float $mutual, array $breakdown): string
    {
        $level = $mutual >= 80 ? 'sangat tinggi' : ($mutual >= 60 ? 'baik' : ($mutual >= 40 ? 'cukup' : 'rendah'));
        $top = collect($breakdown)->sortDesc()->take(2)->keys()->implode(' dan ');
        $base = 'Kecocokan '.$user->displayName().' dan '.$candidate->displayName().' dinilai '.$level.' ('.round($mutual).'/100).';
        if ($top) {
            $base .= ' Kekuatan utama: '.$top.'.';
        }

        return $base.' Laporan ini dihitung dari dimensi usia, lokasi, preferensi, kepribadian, minat, gaya hidup, tujuan, dan perilaku.';
    }

    public function forUser(User $user, int $perPage = 20)
    {
        return CompatibilityReport::where('user_id', $user->id)->with('candidate')->latest('id')->paginate($perPage);
    }
}
