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
            $report = CompatibilityReport::updateOrCreate(
                ['user_id' => $user->id, 'candidate_id' => $candidate->id],
                [
                    'score' => round((float) ($score['mutual'] ?? 0), 2),
                    'breakdown' => ['score' => $score, 'explanation' => $explanation],
                    'summary' => $this->summarize($user, $candidate, (float) ($score['mutual'] ?? 0), $score['breakdown'] ?? []),
                ]
            );
            $this->audit->log('compatibility_report.generated', $user, $report, [], ['candidate_id' => $candidate->id]);

            return $report->fresh();
        });
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
