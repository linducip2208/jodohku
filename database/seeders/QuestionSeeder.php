<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuestionnaireVersion;
use App\Models\QuestionOption;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $version = QuestionnaireVersion::firstOrCreate(
            ['version' => 1],
            ['title' => 'Kuesioner Jodohku v1', 'description' => 'Baseline compatibility questionnaire (ID).', 'is_active' => true]
        );

        $categories = [
            ['key' => 'personality', 'name' => 'Kepribadian', 'description' => 'Gaya sosial dan temperamen.', 'sort_order' => 1],
            ['key' => 'lifestyle', 'name' => 'Gaya Hidup', 'description' => 'Kebiasaan harian.', 'sort_order' => 2],
            ['key' => 'values', 'name' => 'Nilai Hidup', 'description' => 'Agama, keluarga, prinsip.', 'sort_order' => 3],
            ['key' => 'relationship', 'name' => 'Relasi', 'description' => 'Tujuan dan ekspektasi hubungan.', 'sort_order' => 4],
            ['key' => 'interests', 'name' => 'Minat', 'description' => 'Hobi dan aktivitas.', 'sort_order' => 5],
            ['key' => 'compatibility', 'name' => 'Kecocokan', 'description' => 'Kesiapan komitmen.', 'sort_order' => 6],
        ];
        $catIds = [];
        foreach ($categories as $c) {
            $row = QuestionCategory::firstOrCreate(['key' => $c['key']], $c + ['is_active' => true]);
            $catIds[$c['key']] = $row->id;
        }

        $questions = [
            ['cat' => 'personality', 'text' => 'Saat akhir pekan, kamu lebih suka?', 'weight' => 3, 'options' => [
                ['t' => 'Di rumah, santai bersama keluarga', 'v' => 'homebody', 's' => 80],
                ['t' => 'Nongkrong bersama teman', 'v' => 'social', 's' => 60],
                ['t' => 'Petualangan / traveling singkat', 'v' => 'adventurous', 's' => 70],
                ['t' => 'Fokus kerja / belajar', 'v' => 'ambitious', 's' => 50],
            ]],
            ['cat' => 'personality', 'text' => 'Bagaimana kamu mengambil keputusan penting?', 'weight' => 2, 'options' => [
                ['t' => 'Musyawarah dengan keluarga', 'v' => 'deliberative', 's' => 80],
                ['t' => 'Cepat dan intuitif', 'v' => 'intuitive', 's' => 55],
                ['t' => 'Analisa data mendalam', 'v' => 'analytical', 's' => 65],
                ['t' => 'Minta nasihat orang terpercaya', 'v' => 'consultative', 's' => 75],
            ]],
            ['cat' => 'lifestyle', 'text' => 'Kebiasaan merokok?', 'weight' => 2, 'options' => [
                ['t' => 'Tidak merokok', 'v' => 'no', 's' => 90],
                ['t' => 'Kadang-kadang', 'v' => 'sometimes', 's' => 40],
                ['t' => 'Sering', 'v' => 'often', 's' => 10],
            ]],
            ['cat' => 'lifestyle', 'text' => 'Seberapa penting olahraga rutin?', 'weight' => 1, 'options' => [
                ['t' => 'Sangat penting', 'v' => 'very', 's' => 90],
                ['t' => 'Biasa saja', 'v' => 'neutral', 's' => 60],
                ['t' => 'Tidak penting', 'v' => 'low', 's' => 30],
            ]],
            ['cat' => 'values', 'text' => 'Peran agama dalam kehidupan sehari-hari?', 'weight' => 3, 'options' => [
                ['t' => 'Sangat sentral', 'v' => 'central', 's' => 95],
                ['t' => 'Penting', 'v' => 'important', 's' => 75],
                ['t' => 'Pribadi / fleksibel', 'v' => 'personal', 's' => 50],
            ]],
            ['cat' => 'values', 'text' => 'Pandangan tentang mengatur keuangan keluarga?', 'weight' => 2, 'options' => [
                ['t' => 'Disiplin menabung', 'v' => 'saver', 's' => 90],
                ['t' => 'Seimbang', 'v' => 'balanced', 's' => 70],
                ['t' => 'Nikmati hidup dulu', 'v' => 'spender', 's' => 35],
            ]],
            ['cat' => 'relationship', 'text' => 'Tujuan utama mencari pasangan saat ini?', 'weight' => 5, 'options' => [
                ['t' => 'Menikah', 'v' => 'marriage', 's' => 100],
                ['t' => 'Hubungan serius', 'v' => 'serious_relationship', 's' => 85],
                ['t' => 'Pacaran / penjajakan', 'v' => 'dating', 's' => 60],
                ['t' => 'Pertemanan dulu', 'v' => 'friendship', 's' => 40],
            ]],
            ['cat' => 'relationship', 'text' => 'Pandangan tentang anak?', 'weight' => 3, 'options' => [
                ['t' => 'Ingin punya anak', 'v' => 'want', 's' => 90],
                ['t' => 'Belum yakin', 'v' => 'unsure', 's' => 55],
                ['t' => 'Tidak ingin anak', 'v' => 'dont_want', 's' => 20],
            ]],
            ['cat' => 'interests', 'text' => 'Aktivitas favorit untuk kencan pertama?', 'weight' => 1, 'options' => [
                ['t' => 'Ngopi santai', 'v' => 'coffee', 's' => 70],
                ['t' => 'Makan malam', 'v' => 'dinner', 's' => 75],
                ['t' => 'Aktivitas outdoor', 'v' => 'outdoor', 's' => 65],
                ['t' => 'Acara budaya / kajian', 'v' => 'cultural', 's' => 80],
            ]],
            ['cat' => 'compatibility', 'text' => 'Kesiapan menikah dalam 1–2 tahun?', 'weight' => 4, 'options' => [
                ['t' => 'Sangat siap', 'v' => 'ready', 's' => 100],
                ['t' => 'Siap dengan persiapan', 'v' => 'conditional', 's' => 75],
                ['t' => 'Belum siap', 'v' => 'not_ready', 's' => 25],
            ]],
        ];

        foreach ($questions as $idx => $q) {
            $question = Question::firstOrCreate(
                ['question_text' => $q['text'], 'questionnaire_version_id' => $version->id],
                [
                    'question_category_id' => $catIds[$q['cat']],
                    'category_key' => $q['cat'],
                    'type' => 'single_choice',
                    'help_text' => null,
                    'sort_order' => $idx + 1,
                    'weight' => $q['weight'],
                    'is_required' => true,
                    'is_active' => true,
                ]
            );
            foreach ($q['options'] as $oi => $o) {
                QuestionOption::firstOrCreate(
                    ['question_id' => $question->id, 'option_value' => $o['v']],
                    ['option_text' => $o['t'], 'sort_order' => $oi + 1, 'score' => $o['s']]
                );
            }
        }
    }
}
