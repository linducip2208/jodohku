<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use App\Models\Question;
use App\Models\QuestionnaireAnswer;
use App\Models\User;
use App\Services\ChatService;
use App\Services\CreditService;
use App\Services\LikeService;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;

/**
 * Fictional demo interactions between seeded members, built through the
 * real service layer (likes → canonical matches → conversations → messages,
 * questionnaire answers, one premium subscription, starter credits).
 * Idempotent: safe to re-run.
 */
class DemoInteractionSeeder extends Seeder
{
    public function run(): void
    {
        $likes = app(LikeService::class);
        $chat = app(ChatService::class);

        $andi = User::where('email', 'andi.pratama@example.test')->first();
        $siti = User::where('email', 'siti.rahayu@example.test')->first();
        $budi = User::where('email', 'budi.santoso@example.test')->first();
        $putri = User::where('email', 'putri.ayu@example.test')->first();
        if (! $andi || ! $siti || ! $budi || ! $putri) {
            return;
        }

        // Mutual likes → canonical matches (Andi↔Siti, Budi→Putri one-sided).
        foreach ([[$andi, $siti], [$siti, $andi], [$budi, $putri]] as [$a, $b]) {
            try {
                $likes->like($a->fresh(), $b->fresh());
            } catch (\Throwable) {
            }
        }

        // Real conversation with history between the matched pair.
        try {
            $conv = $chat->findOrCreateDirect($andi->fresh(), $siti->fresh());
            if ($conv->messages()->count() === 0) {
                $dialog = [
                    [$andi, 'Halo Siti! Senang bisa match denganmu.'],
                    [$siti, 'Halo juga Andi! Salam kenal.'],
                    [$andi, 'Akhir pekan biasanya ke mana?'],
                ];
                foreach ($dialog as [$sender, $body]) {
                    try {
                        $chat->sendMessage($conv->fresh(), $sender->fresh(), ['body' => $body]);
                    } catch (\Throwable $e) {
                        $this->command?->warn('Seed chat skipped: '.$e->getMessage());
                    }
                }
                $chat->markRead($conv->fresh(), $siti->fresh());
            }
        } catch (\Throwable) {
        }

        // Questionnaire answers for matching depth.
        try {
            $questions = Question::where('is_active', true)->take(5)->get();
            foreach ([$andi, $siti] as $u) {
                foreach ($questions as $q) {
                    $opt = $q->options()->first();
                    QuestionnaireAnswer::firstOrCreate(
                        ['user_id' => $u->id, 'question_id' => $q->id],
                        [
                            'questionnaire_version_id' => $q->questionnaire_version_id,
                            'question_option_id' => $opt?->id,
                            'answer_score' => 80,
                            'importance' => 'important',
                        ]
                    );
                }
            }
        } catch (\Throwable) {
        }

        // One premium subscriber + starter credits (fictional demo).
        try {
            $plan = MembershipPlan::where('code', 'premium_monthly')->first();
            if ($plan && ! $andi->isPremium()) {
                app(SubscriptionService::class)->activate($andi->fresh(), $plan);
            }
            app(CreditService::class)->award($putri->fresh(), 50, 'Demo starter bonus');
        } catch (\Throwable) {
        }
    }
}
