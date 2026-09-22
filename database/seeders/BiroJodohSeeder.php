<?php

namespace Database\Seeders;

use App\Enums\ConsultationStatus;
use App\Enums\CourtshipStage;
use App\Enums\CourtshipStatus;
use App\Enums\SuccessStoryStatus;
use App\Models\CompatibilityReport;
use App\Models\Consultation;
use App\Models\Counselor;
use App\Models\Courtship;
use App\Models\SuccessStory;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BiroJodohSeeder extends Seeder
{
    public function run(): void
    {
        // Counselors (fictional bureau staff).
        foreach ([
            ['name' => 'Ustzh. Maryam', 'email' => 'maryam.konselor@example.test', 'specialty' => 'Pranikah & Keluarga', 'bio' => 'Konselor pranikah berpengalaman 10 tahun.'],
            ['name' => 'Bpk. Hidayat', 'email' => 'hidayat.konselor@example.test', 'specialty' => 'Komunikasi Pasangan', 'bio' => 'Fokus komunikasi dan resolusi konflik.'],
        ] as $c) {
            $user = User::firstOrCreate(['email' => $c['email']], [
                'name' => $c['name'], 'password' => Hash::make('password'),
                'display_name' => $c['name'], 'city' => 'Jakarta',
            ]);
            Counselor::firstOrCreate(['user_id' => $user->id], [
                'specialty' => $c['specialty'], 'bio' => $c['bio'], 'is_active' => true,
            ]);
        }

        // Demo courtship between the first two seeded members.
        $members = User::whereIn('email', ['andi.pratama@example.test', 'siti.rahayu@example.test'])->get();
        if ($members->count() === 2) {
            [$a, $b] = [$members[0], $members[1]];
            [$u1, $u2] = UserMatch::canonical($a->id, $b->id);
            $match = UserMatch::firstOrCreate(
                ['user_a_id' => $u1, 'user_b_id' => $u2],
                ['is_active' => true, 'matched_at' => now()->subDays(20)]
            );
            $courtship = Courtship::firstOrCreate(
                ['initiator_id' => $a->id, 'partner_id' => $b->id, 'status' => CourtshipStatus::Active],
                [
                    'match_id' => $match->id, 'stage' => CourtshipStage::Taaruf,
                    'guardian_name' => 'H. Slamet', 'guardian_relation' => 'Ayah',
                    'guardian_approved_at' => now()->subDays(3),
                    'stage_history' => [['stage' => 'kenalan', 'at' => now()->subDays(20)->toDateTimeString(), 'by' => $a->id]],
                    'started_at' => now()->subDays(20),
                ]
            );

            $counselor = Counselor::active()->first();
            if ($counselor) {
                Consultation::firstOrCreate(
                    ['counselor_id' => $counselor->id, 'user_id' => $a->id, 'topic' => 'Kesiapan menikah'],
                    [
                        'scheduled_at' => now()->subDays(2), 'duration_minutes' => 30,
                        'status' => ConsultationStatus::Completed, 'decided_at' => now()->subDays(2),
                    ]
                );
                CompatibilityReport::firstOrCreate(
                    ['user_id' => $a->id, 'candidate_id' => $b->id],
                    ['score' => 87.5, 'summary' => 'Demo compatibility report.', 'breakdown' => ['demo' => true]]
                );
            }

            SuccessStory::firstOrCreate(
                ['user_id' => $a->id, 'partner_name' => 'Siti Rahayu'],
                [
                    'story' => 'Kami dipertemukan Jodohku, menjalani taaruf didampingi wali dan konselor, lalu menikah dengan bahagia. Terima kasih biro jodoh terbaik!',
                    'status' => SuccessStoryStatus::Published, 'published_at' => now()->subDays(30),
                ]
            );
        }

        $budiId = User::where('email', 'budi.santoso@example.test')->value('id');
        if ($budiId) {
            SuccessStory::firstOrCreate(
                ['user_id' => $budiId, 'partner_name' => 'Dewi Lestari'],
                [
                    'story' => 'Berawal dari match, berlanjut ke taaruf yang serius. Kini kami sudah menikah dan dikaruniai keluarga kecil yang bahagia.',
                    'status' => SuccessStoryStatus::Published, 'published_at' => now()->subDays(60),
                ]
            );
        }
    }
}
