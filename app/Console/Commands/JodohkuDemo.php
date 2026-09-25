<?php

namespace App\Console\Commands;

use App\Models\Forum;
use App\Models\Gift;
use App\Models\Interest;
use App\Models\MembershipPlan;
use App\Models\NotificationPreference;
use App\Models\ProfanityWord;
use App\Models\ProfilePhoto;
use App\Models\Question;
use App\Models\User;
use App\Services\Demo\DemoPhotoProvider;
use App\Services\MatchingEngine;
use Database\Seeders\GiftSeeder;
use Database\Seeders\InterestSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ProfanitySeeder;
use Database\Seeders\QuestionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Commercial demo dataset generator.
 *
 * php artisan jodohku:demo --users=5000 --seed=20260923
 *
 * Deterministic (same seed → same dataset), bulk-insert based, with
 * progress output. All rows go through the REAL tables/models; matching
 * scores for matched pairs are computed with the real MatchingEngine.
 * See DEMO.md for the full contract (options, photos, reset, safety).
 */
class JodohkuDemo extends Command
{
    protected $signature = 'jodohku:demo
        {--users=5000 : Number of demo users to create}
        {--seed=20260923 : Deterministic seed}
        {--photos= : Total demo photos (default users x 1.5)}
        {--messages= : Total demo messages (default users x 10)}
        {--posts= : Total demo posts (default users x 0.4, min 200)}
        {--comments= : Total demo comments (default posts x 5)}
        {--groups= : Total demo groups (default users/100, min 10)}
        {--events= : Total demo events (default users/50, min 20)}
        {--forums= : Total demo forum threads (default users/5, min 200)}
        {--fresh : migrate:fresh + base seed before demo (DESTRUCTIVE)}
        {--force : Allow production + wipe existing demo data first}
        {--no-photos : Skip photo generation}
        {--no-chat : Skip conversations/messages}
        {--no-community : Skip community data}
        {--no-taaruf : Skip taaruf journeys}
        {--no-payments : Skip subscriptions/credits/payments}
        {--dry-run : Show the plan without writing anything}';

    protected $description = 'Generate a realistic commercial demo dataset (default 5000 users)';

    protected string $passwordHash;

    protected int $demoSeed = 20260923;

    protected array $stats = [];

    public function handle(DemoPhotoProvider $photos): int
    {
        $users = max(1, (int) $this->option('users'));
        $seed = (int) $this->option('seed');
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $plan = [
            'users' => $users,
            'photos' => $this->option('photos') !== null ? (int) $this->option('photos') : (int) round($users * (float) config('demo.photos_per_user', 1.5)),
            'messages' => $this->option('messages') !== null ? (int) $this->option('messages') : $users * 10,
            'posts' => $this->option('posts') !== null ? (int) $this->option('posts') : max(200, (int) round($users * 0.4)),
            'groups' => $this->option('groups') !== null ? (int) $this->option('groups') : max(10, (int) round($users / 100)),
            'events' => $this->option('events') !== null ? (int) $this->option('events') : max(20, (int) round($users / 50)),
            'threads' => $this->option('forums') !== null ? (int) $this->option('forums') : max(200, (int) round($users / 5)),
        ];
        $plan['comments'] = $this->option('comments') !== null ? (int) $this->option('comments') : $plan['posts'] * 5;

        $this->info('Jodohku Demo Seeder');
        $this->line('────────────────────────────');
        foreach ($plan as $k => $v) {
            $this->line(sprintf('%-10s %d', ucfirst($k), $v));
        }
        $this->line(sprintf('%-10s %d', 'Seed', $seed));

        if ($dry) {
            $this->comment('Dry run — nothing written. Add --force/options and re-run to execute.');
            $this->line('Sections: users/profiles'.($this->option('no-photos') ? '' : '/photos').($this->option('no-payments') ? '' : '/payments').'/likes/matches'.($this->option('no-chat') ? '' : '/chat').($this->option('no-taaruf') ? '' : '/taaruf').($this->option('no-community') ? '' : '/community').'/safety');

            return self::SUCCESS;
        }

        if (app()->isProduction() && ! $force) {
            $this->error('Refusing to seed demo data in production without --force.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $force && ! $this->confirm('Fresh will WIPE the database. Continue?')) {
                return self::FAILURE;
            }
            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', ['--force' => true]);
        }

        $existing = User::where('is_demo', true)->count();
        if ($existing > 0 && ! $force) {
            $this->error("Found {$existing} existing demo users. Re-run with --force to wipe them first, or jodohku:demo:reset --confirm.");

            return self::FAILURE;
        }
        if ($existing > 0) {
            $this->call('jodohku:demo:reset', ['--confirm' => true]);
        }

        mt_srand($seed);
        $this->demoSeed = $seed;
        $this->passwordHash = Hash::make('password');
        $started = microtime(true);

        $this->ensureBaseData();
        // Per-phase reseeds: each phase is deterministic on its own, immune
        // to upstream draw-count drift (DB ordering, conditional draws).
        $this->reseed('users');
        $ids = $this->seedUsers($users);
        if (! $this->option('no-photos')) {
            $this->reseed('photos');
            $this->seedPhotos($ids, $plan['photos'], $photos);
        }
        if (! $this->option('no-payments')) {
            $this->reseed('monetization');
            $this->seedMonetization($ids);
        }
        $this->reseed('likes');
        $matches = $this->seedLikesAndMatches($ids);
        if (! $this->option('no-chat')) {
            $this->reseed('chat');
            $this->seedChat($ids, $matches, $plan['messages']);
        }
        if (! $this->option('no-taaruf')) {
            $this->reseed('taaruf');
            $this->seedTaaruf($ids, $matches);
        }
        if (! $this->option('no-community')) {
            $this->reseed('community');
            $this->seedCommunity($ids, $plan);
            $this->reseed('social');
            $this->seedSocialGraph($ids, $plan);
        }
        $this->reseed('safety');
        $this->seedSafety($ids);

        $this->line('────────────────────────────');
        foreach ($this->stats as $k => $v) {
            $this->line(sprintf('%-14s %d', ucfirst($k), $v));
        }
        $this->info(sprintf('Completed in %.1fs', microtime(true) - $started));
        $this->comment('Demo login password for every demo account: password');

        return self::SUCCESS;
    }

    // ---------- helpers ----------

    protected function pick(array $pool): mixed
    {
        return $pool[mt_rand(0, count($pool) - 1)];
    }

    /** Restart the deterministic stream for a phase (see handle()). */
    protected function reseed(string $phase): void
    {
        mt_srand(crc32($this->demoSeed.':'.$phase));
    }

    /** Gender as plain string (models cast it to a BackedEnum). */
    protected function genderOf(object $u): string
    {
        $g = $u->gender ?? null;

        return $g instanceof \BackedEnum ? $g->value : (string) $g;
    }

    protected function chance(float $p): bool
    {
        return (mt_rand() / mt_getrandmax()) < $p;
    }

    protected function bulk(string $table, array $rows, int $chunk = 500): void
    {
        foreach (array_chunk($rows, $chunk) as $c) {
            DB::table($table)->insert($c);
        }
    }

    protected function ts(int $maxDaysAgo = 120): string
    {
        return now()->subDays(mt_rand(0, $maxDaysAgo))->subHours(mt_rand(0, 23))->toDateTimeString();
    }

    // ---------- base data ----------

    protected function ensureBaseData(): void
    {
        foreach ([
            [Interest::class, InterestSeeder::class],
            [Question::class, QuestionSeeder::class],
            [MembershipPlan::class, PlanSeeder::class],
            [Gift::class, GiftSeeder::class],
            [ProfanityWord::class, ProfanitySeeder::class],
        ] as [$model, $seeder]) {
            try {
                if ($model::count() === 0) {
                    $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
                }
            } catch (\Throwable $e) {
                $this->warn('Base seeder skipped: '.class_basename($seeder).' ('.$e->getMessage().')');
            }
        }
    }

    // ---------- users ----------

    protected function seedUsers(int $n): array
    {
        $maleFirst = ['Andi', 'Budi', 'Rizky', 'Agus', 'Fajar', 'Hendra', 'Dimas', 'Eko', 'Wahyu', 'Bagus', 'Galih', 'Ilham', 'Dani', 'Joko', 'Rudi', 'Anton', 'Farhan', 'Hakim', 'Irfan', 'Reza', 'Fikri', 'Alif', 'Bima', 'Chandra', 'Denny', 'Fadli', 'Gilang', 'Hadi', 'Irwan', 'Nanda', 'Pandu', 'Raka', 'Surya', 'Taufik', 'Yusuf', 'Zaenal', 'Ega', 'Oscar', 'Qomar', 'Vicky'];
        $femaleFirst = ['Siti', 'Dewi', 'Putri', 'Maya', 'Intan', 'Rina', 'Ayu', 'Sari', 'Nina', 'Dina', 'Rani', 'Laras', 'Aulia', 'Bella', 'Citra', 'Dinda', 'Fitri', 'Gita', 'Hana', 'Indah', 'Kirana', 'Luna', 'Melati', 'Nadia', 'Ratna', 'Salsa', 'Vina', 'Winda', 'Yulia', 'Zahra', 'Anisa', 'Desi', 'Eka', 'Farah', 'Ira', 'Lina', 'Novi', 'Shinta', 'Tari', 'Vera'];
        $last = ['Pratama', 'Santoso', 'Rahayu', 'Lestari', 'Ramadhan', 'Wijaya', 'Kusuma', 'Nugroho', 'Permata', 'Gunawan', 'Marlina', 'Saputra', 'Hidayat', 'Kurniawan', 'Setiawan', 'Hartono', 'Nasution', 'Siregar', 'Wibowo', 'Mahendra', 'Putra', 'Darma', 'Anggraini', 'Wulandari', 'Nugraha', 'Firmansyah', 'Lubis', 'Pangestu', 'Raharjo', 'Tanjung', 'Utami', 'Yuliana', 'Zakaria', 'Halim', 'Purba', 'Sidabutar', 'Wijayanti', 'Puspita', 'Darmawan', 'Setiono'];
        $cities = [
            ['Jakarta', 'DKI Jakarta', -6.21, 106.85, 18], ['Bandung', 'Jawa Barat', -6.91, 107.61, 12],
            ['Surabaya', 'Jawa Timur', -7.26, 112.75, 10], ['Medan', 'Sumatera Utara', 3.60, 98.68, 6],
            ['Semarang', 'Jawa Tengah', -6.97, 110.42, 7], ['Yogyakarta', 'DIY', -7.80, 110.36, 6],
            ['Makassar', 'Sulawesi Selatan', -5.14, 119.42, 5], ['Palembang', 'Sumatera Selatan', -2.99, 104.76, 4],
            ['Tangerang', 'Banten', -6.17, 106.63, 5], ['Bekasi', 'Jawa Barat', -6.24, 106.99, 5],
            ['Depok', 'Jawa Barat', -6.40, 106.82, 4], ['Bogor', 'Jawa Barat', -6.60, 106.80, 4],
            ['Malang', 'Jawa Timur', -7.98, 112.62, 4], ['Denpasar', 'Bali', -8.67, 115.22, 3],
            ['Solo', 'Jawa Tengah', -7.57, 110.82, 2], ['Balikpapan', 'Kalimantan Timur', -1.27, 116.83, 1],
            ['Banjarmasin', 'Kalimantan Selatan', -3.32, 114.59, 1], ['Pekanbaru', 'Riau', 0.51, 101.44, 1],
            ['Padang', 'Sumatera Barat', -0.95, 100.35, 1], ['Bandar Lampung', 'Lampung', -5.43, 105.27, 1],
        ];
        $cityBag = [];
        foreach ($cities as $c) {
            for ($i = 0; $i < $c[4]; $i++) {
                $cityBag[] = $c;
            }
        }
        $occupations = ['Software Engineer', 'Guru', 'Perawat', 'Dokter', 'Wirausaha', 'Desainer Grafis', 'Marketing', 'Akuntan', 'Arsitek', 'Fotografer', 'Pilot', 'Apoteker', 'Barista', 'Chef', 'Content Creator', 'Data Analyst', 'Dosen', 'Insinyur', 'Jurnalis', 'Karyawan Swasta', 'Konsultan', 'Mahasiswa Pascasarjana', 'Notaris', 'Pegawai Bank', 'Pengacara', 'PNS', 'Programmer', 'Psikolog', 'Teknisi', 'Translator'];
        $educations = ['SMA', 'D3', 'S1', 'S1', 'S1', 'S2'];
        $religions = ['Islam', 'Islam', 'Islam', 'Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha'];
        $goals = ['marriage', 'marriage', 'marriage', 'serious_relationship', 'serious_relationship', 'dating'];
        $headlines = ['Serius mencari pasangan hidup', 'Santai tapi serius menikah', 'Mencari teman hidup seiman', 'Siap berkomitmen', 'Ingin membangun keluarga sakinah', 'Mencari yang sefrekuensi', 'Fokus masa depan bersama', 'Terbuka untuk taaruf', 'Mencari pendamping setia', 'Siap menikah tahun ini'];
        $bios = ['Saya orang yang sederhana dan pekerja keras. Suka kuliner dan traveling bareng keluarga.', 'Perkenalkan, saya suka ngobrol santai tentang masa depan. Keluarga adalah prioritas.', 'Hobi saya membaca dan olahraga pagi. Mencari pasangan yang saling mendukung.', 'Saya bekerja di bidang kreatif. Suka musik, kopi, dan diskusi mendalam.', 'Anak rumahan yang suka masak. Ingin membangun rumah tangga yang hangat.', 'Saya pendengar yang baik dan suka belajar hal baru setiap hari.', 'Aktif di komunitas sosial. Mencari pasangan yang juga peduli sesama.', 'Saya suka traveling dan fotografi. Butuh teman hidup yang asyik diajak jalan.', 'Pekerja kantoran biasa yang punya mimpi besar untuk keluarga kecil.', 'Saya religius dan dekat dengan keluarga. Taaruf dengan adab adalah jalan saya.', 'Suka olahraga lari dan hidup sehat. Mencari yang sevisi soal kesehatan.', 'Saya humoris tapi bertanggung jawab. Siap diajak serius ke jenjang nikah.'];

        $premiumRatio = (float) config('demo.premium_ratio', 0.22);
        $vipRatio = (float) config('demo.vip_ratio', 0.07);
        $verifiedRatio = (float) config('demo.verified_ratio', 0.32);
        $activeRatio = (float) config('demo.active_ratio', 0.5);

        $interestIds = Interest::orderBy('id')->pluck('id')->all();
        $questions = Question::where('is_active', true)->orderBy('id')->with('options')->get();
        $now = now()->toDateTimeString();

        $users = [];
        $profiles = [];
        $prefs = [];
        $privacy = [];
        $notifs = [];
        $userInterests = [];
        $answers = [];

        $bar = $this->output->createProgressBar($n);
        $bar->setFormat('Users %current%/%max% [%bar%] %percent:3s%%');
        for ($seq = 1; $seq <= $n; $seq++) {
            $isMale = $this->chance(0.5);
            $first = $this->pick($isMale ? $maleFirst : $femaleFirst);
            $name = $first.' '.$this->pick($last);
            $city = $this->pick($cityBag);
            $age = mt_rand(21, 45);
            $dob = now()->subYears($age)->subDays(mt_rand(0, 364))->toDateString();
            $isVip = $this->chance($vipRatio);
            $isPremium = $isVip || $this->chance($premiumRatio);
            $isActiveUser = $this->chance($activeRatio);
            $status = $this->chance(0.985) ? 'active' : ($this->chance(0.7) ? 'inactive' : 'suspended');
            $created = $this->ts(180);

            $users[] = [
                'name' => $name,
                'email' => sprintf('demo%05d@demo.jodohku.test', $seq),
                'email_verified_at' => $created,
                'phone' => '+628'.str_pad((string) (1000000000 + $seq), 10, '0', STR_PAD_LEFT),
                'password' => $this->passwordHash,
                'remember_token' => substr(hash('sha256', 'demo'.$this->demoSeed.$seq), 0, 10),
                'account_type' => 'real',
                'role' => $isPremium ? 'premium' : 'member',
                'status' => $status,
                'username' => 'demo_user_'.$seq,
                'display_name' => $first,
                'date_of_birth' => $dob,
                'gender' => $isMale ? 'male' : 'female',
                'is_verified' => $this->chance($verifiedRatio),
                'is_premium' => $isPremium,
                'is_demo' => true,
                'is_online' => $isActiveUser && $this->chance(0.16),
                'last_active_at' => $isActiveUser ? now()->subHours(mt_rand(0, 72))->toDateTimeString() : now()->subDays(mt_rand(8, 120))->toDateTimeString(),
                'latitude' => round($city[2] + (mt_rand(-150, 150) / 1000), 7),
                'longitude' => round($city[3] + (mt_rand(-150, 150) / 1000), 7),
                'city' => $city[0],
                'province' => $city[1],
                'country' => 'Indonesia',
                'avatar_path' => null,
                'profile_completion' => mt_rand(65, 100),
                'created_at' => $created,
                'updated_at' => $created,
            ];
            // Row assembly continues after id mapping below.
            $bar->advance();
            if ($seq % 500 === 0) {
                $this->flushUserChunk($users, $profiles, $prefs, $privacy, $notifs, $userInterests, $answers, $interestIds, $questions, $occupations, $educations, $religions, $goals, $headlines, $bios, $isMale, $now);
            }
        }
        $bar->finish();
        $this->line('');
        $this->flushUserChunk($users, $profiles, $prefs, $privacy, $notifs, $userInterests, $answers, $interestIds, $questions, $occupations, $educations, $religions, $goals, $headlines, $bios, $isMale, $now);

        $ids = User::where('is_demo', true)->orderBy('id')->pluck('id')->all();
        $this->stats['users'] = count($ids);

        return $ids;
    }

    /**
     * Build dependent rows for buffered users, insert the chunk, then map
     * ids back by the unique demo email. Buffers are cleared by reference.
     */
    protected function flushUserChunk(array &$users, array &$profiles, array &$prefs, array &$privacy, array &$notifs, array &$userInterests, array &$answers, array $interestIds, $questions, array $occupations, array $educations, array $religions, array $goals, array $headlines, array $bios, bool $lastMale, string $now): void
    {
        if (empty($users)) {
            return;
        }
        // Dependents need ids first: insert users, then map by email.
        $emails = array_column($users, 'email');
        $this->bulk('users', $users);
        $idByEmail = User::whereIn('email', $emails)->pluck('id', 'email')->all();

        foreach ($users as $u) {
            $id = $idByEmail[$u['email']] ?? null;
            if (! $id) {
                continue;
            }
            $isMale = $u['gender'] === 'male';
            $age = (int) abs(now()->diffInYears($u['date_of_birth']));
            $profiles[] = [
                'user_id' => $id,
                'headline' => $this->pick($headlines),
                'bio' => $this->pick($bios),
                'occupation' => $this->pick($occupations),
                'education' => $this->pick($educations),
                'religion' => $this->pick($religions),
                'marital_status' => $this->chance(0.82) ? 'single' : 'divorced',
                'relationship_goal' => $this->pick($goals),
                'height_cm' => $isMale ? mt_rand(160, 185) : mt_rand(150, 172),
                'languages' => 'Indonesia',
                'is_complete' => true,
                'created_at' => $u['created_at'],
                'updated_at' => $u['created_at'],
            ];
            $prefs[] = [
                'user_id' => $id,
                'min_age' => max(18, $age - mt_rand(2, 6)),
                'max_age' => min(60, $age + mt_rand(2, 8)),
                'gender_preference' => $this->chance(0.98) ? ($isMale ? 'female' : 'male') : ($isMale ? 'male' : 'female'),
                'max_distance_km' => $this->pick([50, 100, 100, 200, 200, 500]),
                'city' => $this->chance(0.3) ? $u['city'] : null,
                'relationship_goal' => $this->chance(0.6) ? $this->pick($goals) : null,
                'verified_only' => $this->chance(0.05),
                'photo_only' => $this->chance(0.08),
                'created_at' => $u['created_at'],
                'updated_at' => $u['created_at'],
            ];
            $privacy[] = ['user_id' => $id, 'created_at' => $u['created_at'], 'updated_at' => $u['created_at']];
            $notifs[] = array_merge(
                NotificationPreference::defaultsFor($id),
                ['created_at' => $u['created_at'], 'updated_at' => $u['created_at']]
            );

            if ($interestIds) {
                $k = mt_rand(2, 5);
                $pool = $interestIds;
                for ($i = 0; $i < $k && $pool; $i++) {
                    $idx = mt_rand(0, count($pool) - 1);
                    $iid = $pool[$idx];
                    array_splice($pool, $idx, 1);
                    $userInterests[] = ['user_id' => $id, 'interest_id' => $iid, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            if ($questions->isNotEmpty() && $this->chance(0.6)) {
                foreach ($questions->take(10) as $q) {
                    $opt = $q->options->isNotEmpty() ? $q->options[mt_rand(0, $q->options->count() - 1)] : null;
                    $answers[] = [
                        'user_id' => $id,
                        'question_id' => $q->id,
                        'questionnaire_version_id' => $q->questionnaire_version_id,
                        'question_option_id' => $opt?->id,
                        'answer_score' => $opt ? $opt->score : mt_rand(40, 100),
                        'importance' => $this->pick(['neutral', 'important', 'important', 'very_important']),
                        'created_at' => $u['created_at'],
                        'updated_at' => $u['created_at'],
                    ];
                }
            }
        }

        $this->bulk('profiles', $profiles);
        $this->bulk('partner_preferences', $prefs);
        $this->bulk('profile_privacy', $privacy);
        $this->bulk('notification_preferences', $notifs);
        $this->bulk('user_interests', $userInterests);
        $this->bulk('questionnaire_answers', $answers);

        $users = [];
        $profiles = [];
        $prefs = [];
        $privacy = [];
        $notifs = [];
        $userInterests = [];
        $answers = [];
    }

    // ---------- photos ----------

    protected function seedPhotos(array $ids, int $target, DemoPhotoProvider $provider): void
    {
        $names = User::whereIn('id', $ids)->pluck('display_name', 'id')->all();
        // Exact distribution: base photos each + remainder spread (max 5/user).
        $target = max(count($ids), min($target, count($ids) * 5));
        $base = max(1, intdiv($target, count($ids)));
        $extra = $target - $base * count($ids);
        $order = $ids;
        shuffle($order);
        $bonus = array_fill_keys(array_slice($order, 0, $extra), true);
        $rows = [];
        $bar = $this->output->createProgressBar(count($ids));
        $bar->setFormat('Photos %current%/%max% [%bar%] %percent:3s%%');
        foreach ($ids as $id) {
            $count = $base + (isset($bonus[$id]) ? 1 : 0);
            for ($i = 0; $i < $count; $i++) {
                try {
                    $path = $provider->avatar($id, (string) ($names[$id] ?? 'Member'), $i, (string) config('demo.photo_disk', 'public'));
                } catch (\Throwable) {
                    $path = null;
                }
                if ($path) {
                    $rows[] = [
                        'user_id' => $id, 'path' => $path, 'thumbnail_path' => null,
                        'sort_order' => $i, 'is_primary' => $i === 0,
                        'is_approved' => true, 'is_private' => false,
                        'status' => 'approved', 'file_hash' => sha1($path.$id.$i),
                        'width' => 480, 'height' => 600,
                        'created_at' => $this->ts(150), 'updated_at' => now()->toDateTimeString(),
                    ];
                }
            }
            $bar->advance();
            if (count($rows) >= 1000) {
                $this->bulk('profile_photos', $rows, 1000);
                $rows = [];
            }
        }
        $bar->finish();
        $this->line('');
        $this->bulk('profile_photos', $rows, 1000);
        $this->stats['photos'] = ProfilePhoto::whereIn('user_id', $ids)->count();
    }

    // ---------- monetization ----------

    protected function seedMonetization(array $ids): void
    {
        $plans = MembershipPlan::where('is_active', true)->orderBy('id')->get()->keyBy('code');
        $premium = $plans->get('premium_monthly');
        $vip = $plans->get('vip_monthly') ?? $premium;
        if (! $premium) {
            $this->warn('No premium plans found, skipping monetization.');

            return;
        }
        $users = User::whereIn('id', $ids)->where('is_premium', true)->orderBy('id')->get(['id', 'created_at']);
        $subs = [];
        $payments = [];
        $wallets = [];
        $txns = [];
        $now = now();
        foreach ($users as $u) {
            $isVip = $this->chance(0.25);
            $plan = $isVip ? $vip : $premium;
            $start = $now->copy()->subDays(mt_rand(0, 25))->toDateTimeString();
            $subs[] = [
                'ulid' => (string) Str::ulid(), 'user_id' => $u->id,
                'membership_plan_id' => $plan->id, 'status' => 'active',
                'starts_at' => $start, 'ends_at' => $now->copy()->addDays(30)->toDateTimeString(),
                'trial_ends_at' => null, 'cancelled_at' => null, 'auto_renew' => $this->chance(0.7),
                'created_at' => $start, 'updated_at' => $start,
            ];
            $inv = 'DEMO-'.strtoupper(substr(hash('sha256', 'pay'.$u->id), 0, 10));
            $payments[] = [
                'ulid' => (string) Str::ulid(), 'user_id' => $u->id, 'gateway' => 'manual',
                'invoice_number' => $inv, 'amount' => $plan->price, 'total_amount' => $plan->price,
                'currency' => $plan->currency ?? 'IDR', 'status' => 'paid', 'paid_at' => $start,
                'created_at' => $start, 'updated_at' => $start,
            ];
            if ($this->chance(0.75)) {
                $bal = mt_rand(50, 800);
                $wallets[] = ['user_id' => $u->id, 'balance' => $bal, 'lifetime_earned' => $bal, 'lifetime_spent' => 0, 'created_at' => $start, 'updated_at' => $start];
                $txns[] = ['user_id' => $u->id, 'type' => 'bonus', 'amount' => $bal, 'balance_after' => $bal, 'description' => 'Demo starter bonus', 'created_at' => $start, 'updated_at' => $start];
            }
        }
        // Some free users hold small wallets too.
        $free = User::whereIn('id', $ids)->where('is_premium', false)->inRandomOrder()->limit((int) (count($ids) * 0.15))->get(['id']);
        foreach ($free as $u) {
            $bal = mt_rand(10, 120);
            $wallets[] = ['user_id' => $u->id, 'balance' => $bal, 'lifetime_earned' => $bal, 'lifetime_spent' => 0, 'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString()];
            $txns[] = ['user_id' => $u->id, 'type' => 'bonus', 'amount' => $bal, 'balance_after' => $bal, 'description' => 'Demo activity bonus', 'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString()];
        }
        $this->bulk('subscriptions', $subs);
        $this->bulk('payments', $payments);
        // Link payments to their subscriptions.
        $subByUser = DB::table('subscriptions')->whereIn('user_id', $users->pluck('id'))->pluck('id', 'user_id');
        foreach (DB::table('payments')->whereIn('user_id', $users->pluck('id'))->where('invoice_number', 'like', 'DEMO-%')->get(['id', 'user_id']) as $p) {
            if (isset($subByUser[$p->user_id])) {
                DB::table('payments')->where('id', $p->id)->update(['subscription_id' => $subByUser[$p->user_id]]);
            }
        }
        $this->bulk('credit_wallets', $wallets);
        // Wallets needed first for wallet_id on txns (nullable, so attach where possible).
        $walletByUser = DB::table('credit_wallets')->whereIn('user_id', array_column($wallets, 'user_id'))->pluck('id', 'user_id');
        foreach ($txns as &$t) {
            $t['credit_wallet_id'] = $walletByUser[$t['user_id']] ?? null;
        }
        unset($t);
        $this->bulk('credit_transactions', $txns);
        $this->stats['subscriptions'] = count($subs);
        $this->stats['credits'] = count($wallets);

        // Gifts + boosts for flavor.
        $gifts = Gift::where('is_active', true)->orderBy('id')->get(['id', 'credit_price']);
        if ($gifts->isNotEmpty()) {
            $gtx = [];
            for ($i = 0; $i < min(600, count($ids)); $i++) {
                $a = $ids[mt_rand(0, count($ids) - 1)];
                $b = $ids[mt_rand(0, count($ids) - 1)];
                if ($a === $b) {
                    continue;
                }
                $g = $gifts[mt_rand(0, $gifts->count() - 1)];
                $gtx[] = ['gift_id' => $g->id, 'sender_id' => $a, 'receiver_id' => $b, 'quantity' => 1, 'credits_spent' => $g->credit_price, 'note' => 'Demo gift', 'created_at' => $this->ts(60), 'updated_at' => now()->toDateTimeString()];
            }
            $this->bulk('gift_transactions', $gtx);
            $this->stats['gifts'] = count($gtx);
        }
        $boosts = [];
        $candidates = array_values(array_filter($ids, fn ($id) => $this->chance(0.05)));
        foreach (array_slice($candidates, 0, 200) as $id) {
            $start = now()->subMinutes(mt_rand(0, 20))->toDateTimeString();
            $boosts[] = ['user_id' => $id, 'status' => 'active', 'duration_minutes' => 30, 'starts_at' => $start, 'ends_at' => now()->addMinutes(30)->toDateTimeString(), 'views_gained' => mt_rand(5, 120), 'likes_gained' => mt_rand(0, 12), 'created_at' => $start, 'updated_at' => $start];
        }
        $this->bulk('boosts', $boosts);
    }

    // ---------- likes & matches ----------

    protected function seedLikesAndMatches(array $ids): array
    {
        $users = User::whereIn('id', $ids)->where('status', 'active')->orderBy('id')->get(['id', 'gender', 'city']);
        $byGender = ['male' => [], 'female' => []];
        foreach ($users as $u) {
            $byGender[$this->genderOf($u) === 'female' ? 'female' : 'male'][] = $u->id;
        }
        $seen = [];
        $likes = [];
        $now = now()->toDateTimeString();
        $bar = $this->output->createProgressBar(count($users));
        $bar->setFormat('Likes %current%/%max% [%bar%] %percent:3s%%');
        foreach ($users as $u) {
            $mine = $this->genderOf($u);
            $targetGender = $mine === 'female' ? 'male' : 'female';
            $pool = $byGender[$targetGender] ?: $byGender[$mine];
            $k = mt_rand(4, 8);
            for ($i = 0; $i < $k && $pool; $i++) {
                $t = $pool[mt_rand(0, count($pool) - 1)];
                if ($t === $u->id || isset($seen[$u->id.':'.$t])) {
                    continue;
                }
                $seen[$u->id.':'.$t] = true;
                $likes[] = ['liker_id' => $u->id, 'liked_id' => $t, 'is_super' => $this->chance(0.05), 'created_at' => $this->ts(90), 'updated_at' => $now];
            }
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
        $this->bulk('likes', $likes);
        $this->stats['likes'] = count($likes);

        // Explicit mutual pairs (~8% of users) → canonical matches.
        $shuffled = $users->pluck('id')->all();
        shuffle($shuffled);
        $pairs = [];
        $used = [];
        foreach ($shuffled as $id) {
            if (isset($used[$id]) || count($pairs) * 2 >= count($ids) * 0.16) {
                continue;
            }
            $me = $users->firstWhere('id', $id);
            $mine = $me ? $this->genderOf($me) : 'male';
            $pool = $byGender[$mine === 'female' ? 'male' : 'female'] ?? [];
            $pool = array_values(array_filter($pool, fn ($x) => $x !== $id && ! isset($used[$x])));
            if (! $pool) {
                continue;
            }
            $other = $pool[mt_rand(0, count($pool) - 1)];
            $used[$id] = $used[$other] = true;
            $pairs[] = [$id, $other];
        }
        $likeRows = [];
        foreach ($pairs as [$a, $b]) {
            foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
                if (! isset($seen[$x.':'.$y])) {
                    $seen[$x.':'.$y] = true;
                    $likeRows[] = ['liker_id' => $x, 'liked_id' => $y, 'is_super' => false, 'created_at' => $this->ts(80), 'updated_at' => $now];
                }
            }
        }
        $this->bulk('likes', $likeRows);
        $likeMap = DB::table('likes')
            ->whereIn('liker_id', array_merge(array_column($pairs, 0), array_column($pairs, 1)))
            ->get(['id', 'liker_id', 'liked_id'])
            ->keyBy(fn ($r) => $r->liker_id.':'.$r->liked_id);
        $matchRows = [];
        foreach ($pairs as [$a, $b]) {
            [$u1, $u2] = $a <= $b ? [$a, $b] : [$b, $a];
            $likeId = $likeMap[$u1.':'.$u2]->id ?? $likeMap[$u2.':'.$u1]->id ?? null;
            $matchRows[] = [
                'ulid' => (string) Str::ulid(), 'user_a_id' => $u1, 'user_b_id' => $u2,
                'like_id' => $likeId, 'compatibility_score' => null, 'is_active' => true,
                'matched_at' => $this->ts(75), 'unmatched_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->bulk('matches', $matchRows);
        $this->stats['matches'] = count($matchRows);

        // Real compatibility scores for matched pairs via MatchingEngine.
        try {
            $engine = app(MatchingEngine::class);
            $needIds = [];
            foreach ($pairs as [$a, $b]) {
                $needIds[$a] = true;
                $needIds[$b] = true;
            }
            $models = User::whereIn('id', array_keys($needIds))
                ->with(['profile', 'partnerPreference', 'interests', 'questionnaireAnswers'])
                ->withCount(['photos as approved_photos_count' => fn ($q) => $q->where('status', 'approved')])
                ->get()->keyBy('id');
            $scoreRows = [];
            foreach ($pairs as [$a, $b]) {
                if (! isset($models[$a], $models[$b])) {
                    continue;
                }
                try {
                    $r = $engine->scorePair($models[$a], $models[$b]);
                } catch (\Throwable) {
                    continue;
                }
                [$u1, $u2] = $a <= $b ? [$a, $b] : [$b, $a];
                DB::table('matches')->where('user_a_id', $u1)->where('user_b_id', $u2)->update(['compatibility_score' => $r['mutual']]);
                $scoreRows[] = [
                    'user_id' => $u1, 'candidate_id' => $u2,
                    'questionnaire_score' => $r['breakdown']['personality'] ?? 0,
                    'interest_score' => $r['breakdown']['interest'] ?? 0,
                    'preference_score' => $r['breakdown']['preference'] ?? 0,
                    'activity_score' => $r['breakdown']['behavior'] ?? 0,
                    'total_score' => $r['mutual'], 'breakdown' => json_encode($r),
                    'computed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('match_scores')->upsert($scoreRows, ['user_id', 'candidate_id'], ['questionnaire_score', 'interest_score', 'preference_score', 'activity_score', 'total_score', 'breakdown', 'computed_at', 'updated_at']);
        } catch (\Throwable $e) {
            $this->warn('Match scoring skipped: '.$e->getMessage());
        }

        // Super likes + favorites + visitors samples.
        $supers = [];
        foreach (array_slice($likes, 0, (int) (count($ids) * 0.3)) as $l) {
            if ($this->chance(0.2)) {
                $supers[] = ['sender_id' => $l['liker_id'], 'receiver_id' => $l['liked_id'], 'message' => 'Kamu menarik perhatianku!', 'used_at' => $l['created_at'], 'created_at' => $l['created_at'], 'updated_at' => $now];
            }
        }
        $this->bulk('super_likes', $supers);
        $favs = [];
        $favSeen = [];
        for ($i = 0; $i < (int) (count($ids) * 1.5); $i++) {
            $a = $ids[mt_rand(0, count($ids) - 1)];
            $b = $ids[mt_rand(0, count($ids) - 1)];
            if ($a === $b || isset($favSeen[$a.':'.$b])) {
                continue;
            }
            $favSeen[$a.':'.$b] = true;
            $favs[] = ['user_id' => $a, 'favorited_id' => $b, 'created_at' => $this->ts(90), 'updated_at' => $now];
        }
        $this->bulk('favorites', $favs);
        $views = [];
        for ($i = 0; $i < count($ids) * 3; $i++) {
            $a = $ids[mt_rand(0, count($ids) - 1)];
            $b = $ids[mt_rand(0, count($ids) - 1)];
            if ($a !== $b) {
                $views[] = ['profile_user_id' => $b, 'viewer_id' => $a, 'viewed_at' => $this->ts(30), 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->bulk('profile_views', $views);
        $this->stats['visitors'] = count($views);

        return $pairs;
    }

    // ---------- chat ----------

    protected array $chatThemes = [
        'kenalan' => ['Halo! Salam kenal ya.', 'Hai, senang bisa match denganmu.', 'Perkenalkan, aku %s. Kamu sibuk apa akhir-akhir ini?', 'Akhirnya match juga! Cerita dong tentang dirimu.', 'Halo, profilmu menarik. Boleh kenalan lebih jauh?'],
        'hobi' => ['Kamu suka ngapain pas weekend?', 'Aku suka traveling dan kuliner. Kamu?', 'Film favoritmu apa? Aku tim drama Korea sih.', 'Olahraga apa yang kamu suka? Aku lagi rutin lari pagi.', 'Hobi baca buku juga? Lagi baca apa sekarang?'],
        'kerja' => ['Kerjaanmu di bidang apa? Pasti seru ya.', 'Lembur terus nih minggu ini. Kamu gimana?', 'Aku lagi belajar skill baru buat karier. Kamu ada target tahun ini?', 'WFH atau WFO? Aku hybrid, lumayan fleksibel.'],
        'keluarga' => ['Kamu dekat sama keluarga? Aku tiap minggu pulang ke rumah ortu.', 'Anak ke berapa? Aku anak pertama dari tiga bersaudara.', 'Keluargaku sederhana tapi hangat. Keluarga impianmu gimana?', 'Lebaran kemarin mudik ke mana?'],
        'kota' => ['Kamu asli sini atau merantau?', 'Di kotamu ada tempat nongkrong favorit?', 'Aku baru pindah ke sini setahun lalu. Betah sih.', 'Macetnya luar biasa ya hari ini. Kamu berangkat jam berapa?'],
        'serius' => ['Aku serius cari pasangan untuk menikah. Kamu gimana?', 'Menurutmu, hal terpenting dalam pernikahan itu apa?', 'Aku ingin hubungan yang jujur dan terbuka sejak awal.', 'Targetku dua tahun ke depan sudah menikah. Kamu?', 'Bagaimana pandanganmu soal peran suami-istri?'],
        'taaruf' => ['Apakah kamu terbuka menjalani taaruf dengan pendampingan wali?', 'Menurutku taaruf itu indah kalau dijalani dengan adab.', 'Kita bisa diskusi visi pernikahan dulu sebelum melangkah jauh.', 'Aku ingin libatkan orang tua sejak awal. Kamu setuju?'],
        'weekend' => ['Weekend ini ada rencana ke mana?', 'Lagi pengin ngopi santai sambil baca buku nih.', 'Ada rekomendasi tempat makan enak di dekatmu?', 'Nonton bioskop yuk kapan-kapan? Atau terlalu cepat ya, hehe.'],
    ];

    protected function seedChat(array $ids, array $pairs, int $messageTarget): void
    {
        $now = now()->toDateTimeString();
        $convoPairs = $pairs;
        // Top up with extra pairs to reach conversation volume.
        $wantConvos = max(count($pairs), (int) round($messageTarget / 8));
        $have = [];
        foreach ($convoPairs as [$a, $b]) {
            $have[min($a, $b).':'.max($a, $b)] = true;
        }
        $guard = 0;
        while (count($convoPairs) < $wantConvos && $guard++ < $wantConvos * 3) {
            $a = $ids[mt_rand(0, count($ids) - 1)];
            $b = $ids[mt_rand(0, count($ids) - 1)];
            $k = min($a, $b).':'.max($a, $b);
            if ($a !== $b && ! isset($have[$k])) {
                $have[$k] = true;
                $convoPairs[] = [$a, $b];
            }
        }

        $perConvo = max(3, (int) round($messageTarget / max(1, count($convoPairs))));
        // Link matched-pair conversations to their match row.
        $matchByPair = DB::table('matches')->get(['id', 'user_a_id', 'user_b_id'])
            ->keyBy(fn ($m) => $m->user_a_id.':'.$m->user_b_id);
        $convos = [];
        $members = [];
        $messages = [];
        $reads = [];
        $notifs = [];
        $bar = $this->output->createProgressBar(count($convoPairs));
        $bar->setFormat('Chat %current%/%max% [%bar%] %percent:3s%%');
        $pairIdx = 0;
        foreach ($convoPairs as [$a, $b]) {
            [$u1, $u2] = $a <= $b ? [$a, $b] : [$b, $a];
            $mid = $pairIdx++ < count($pairs) ? ($matchByPair[$u1.':'.$u2]->id ?? null) : null;
            $ulid = (string) Str::ulid();
            $lastAt = now()->subDays(mt_rand(0, 30))->subHours(mt_rand(0, 23))->toDateTimeString();
            $convos[] = ['ulid' => $ulid, 'type' => 'direct', 'match_id' => $mid, 'created_by' => $a, 'is_pinned' => false, 'is_archived' => false, 'is_blocked' => false, 'last_message_at' => $lastAt, 'created_at' => $lastAt, 'updated_at' => $lastAt];
            $members[] = ['conversation_id' => null, 'ulid_ref' => $ulid, 'user_id' => $a, 'role' => 'member', 'joined_at' => $lastAt, 'last_read_at' => $lastAt];
            $members[] = ['conversation_id' => null, 'ulid_ref' => $ulid, 'user_id' => $b, 'role' => 'member', 'joined_at' => $lastAt, 'last_read_at' => $lastAt];
            $themeKeys = array_keys($this->chatThemes);
            $theme = $themeKeys[mt_rand(0, count($themeKeys) - 1)];
            $lines = $this->chatThemes[$theme];
            $count = min($perConvo + mt_rand(-2, 4), 16);
            $count = max(2, $count);
            $t = strtotime($lastAt) - $count * mt_rand(300, 3600);
            for ($i = 0; $i < $count; $i++) {
                $sender = $i % 2 === 0 ? $a : $b;
                $t += mt_rand(300, 7200);
                $body = $lines[mt_rand(0, count($lines) - 1)];
                $messages[] = ['conv_ulid' => $ulid, 'sender_id' => $sender, 'body' => $body, 'type' => 'text', 'status' => 'sent', 'ts' => date('Y-m-d H:i:s', $t), 'peer' => $i % 2 === 0 ? $b : $a];
            }
            $bar->advance();
        }
        $bar->finish();
        $this->line('');

        // Insert conversations, map ulid → id, then members + messages.
        foreach (array_chunk($convos, 500) as $chunk) {
            DB::table('conversations')->insert($chunk);
        }
        $convIds = DB::table('conversations')->whereIn('ulid', array_column($convos, 'ulid'))->pluck('id', 'ulid');
        $memberRows = [];
        foreach ($members as $m) {
            $cid = $convIds[$m['ulid_ref']] ?? null;
            if ($cid) {
                $memberRows[] = ['conversation_id' => $cid, 'user_id' => $m['user_id'], 'role' => 'member', 'joined_at' => $m['joined_at'], 'last_read_at' => $m['last_read_at'], 'created_at' => $m['joined_at'], 'updated_at' => $m['joined_at']];
            }
        }
        $this->bulk('conversation_members', $memberRows);
        $msgRows = [];
        foreach ($messages as $m) {
            $cid = $convIds[$m['conv_ulid']] ?? null;
            if (! $cid) {
                continue;
            }
            $msgRows[] = [
                'ulid' => (string) Str::ulid(), 'conversation_id' => $cid, 'sender_id' => $m['sender_id'],
                'body' => $m['body'], 'type' => 'text', 'status' => 'sent',
                'client_message_id' => (string) Str::uuid(), 'is_edited' => false, 'is_system' => false, 'is_ai_generated' => false,
                'created_at' => $m['ts'], 'updated_at' => $m['ts'],
            ];
        }
        $this->bulk('messages', $msgRows);
        // Read receipts (peer read ~70%) + message notifications (20%).
        $msgIds = DB::table('messages')->whereIn('conversation_id', array_values($convIds->all()))->orderBy('id')->get(['id', 'conversation_id', 'sender_id']);
        $byConv = [];
        foreach ($msgIds as $m) {
            $byConv[$m->conversation_id][] = $m;
        }
        $memberByConv = [];
        foreach ($memberRows as $m) {
            $memberByConv[$m['conversation_id']][] = $m['user_id'];
        }
        $readRows = [];
        $msgNotifs = [];
        foreach ($byConv as $cid => $list) {
            $peers = $memberByConv[$cid] ?? [];
            foreach ($list as $m) {
                $other = $peers[0] === $m->sender_id ? ($peers[1] ?? null) : ($peers[0] ?? null);
                if ($other && $this->chance(0.7)) {
                    $readRows[] = ['message_id' => $m->id, 'user_id' => $other, 'read_at' => now()->toDateTimeString(), 'created_at' => $now, 'updated_at' => $now];
                }
                if ($other && $this->chance(0.2)) {
                    $msgNotifs[] = [
                        'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\NewMessage',
                        'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => $other,
                        'data' => json_encode(['type' => 'new_message', 'message_id' => $m->id, 'conversation_id' => $cid, 'sender_id' => $m->sender_id]),
                        'read_at' => $this->chance(0.5) ? now()->toDateTimeString() : null,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
        }
        $this->bulk('message_reads', $readRows);
        $this->bulk('notifications', $msgNotifs);
        $this->stats['conversations'] = count($convos);
        $this->stats['messages'] = count($msgRows);

        // Match notifications for matched pairs.
        $matchNotifs = [];
        foreach ($pairs as [$a, $b]) {
            [$u1, $u2] = $a <= $b ? [$a, $b] : [$b, $a];
            $mid = DB::table('matches')->where('user_a_id', $u1)->where('user_b_id', $u2)->value('id');
            if (! $mid) {
                continue;
            }
            foreach ([[$a, $b], [$b, $a]] as [$me, $other]) {
                $matchNotifs[] = [
                    'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\MatchFound',
                    'notifiable_type' => 'App\\Models\\User', 'notifiable_id' => $me,
                    'data' => json_encode(['type' => 'match_found', 'match_id' => $mid, 'other_user_id' => $other]),
                    'read_at' => $this->chance(0.6) ? now()->toDateTimeString() : null,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        $this->bulk('notifications', $matchNotifs);
        $this->stats['notifications'] = count($msgNotifs) + count($matchNotifs);
    }

    // ---------- taaruf ----------

    protected function seedTaaruf(array $ids, array $pairs): void
    {
        $now = now()->toDateTimeString();
        $stages = ['kenalan', 'taaruf', 'khitbah'];
        $take = max(1, (int) round(count($pairs) * 0.35));
        $subset = array_slice($pairs, 0, $take);
        $rows = [];
        foreach ($subset as [$a, $b]) {
            [$u1, $u2] = $a <= $b ? [$a, $b] : [$b, $a];
            $mid = DB::table('matches')->where('user_a_id', $u1)->where('user_b_id', $u2)->value('id');
            $stage = $this->pick($stages);
            $history = [['stage' => 'kenalan', 'at' => $this->ts(60), 'by' => $a]];
            if ($stage !== 'kenalan') {
                $history[] = ['from' => 'kenalan', 'to' => 'taaruf', 'at' => $this->ts(30), 'by' => $b];
            }
            if ($stage === 'khitbah') {
                $history[] = ['from' => 'taaruf', 'to' => 'khitbah', 'at' => $this->ts(10), 'by' => $a];
            }
            $rows[] = [
                'initiator_id' => $a, 'partner_id' => $b, 'match_id' => $mid, 'conversation_id' => null,
                'stage' => $stage, 'status' => 'active',
                'guardian_name' => $stage === 'khitbah' ? $this->pick(['H. Ahmad', 'Hj. Fatimah', 'Bpk. Sutrisno', 'Ibu Ratna']) : null,
                'guardian_phone' => $stage === 'khitbah' ? '0812'.mt_rand(10000000, 99999999) : null,
                'guardian_relation' => $stage === 'khitbah' ? $this->pick(['Ayah', 'Ibu', 'Kakak', 'Paman']) : null,
                'guardian_approved_at' => $stage === 'khitbah' ? $this->ts(9) : null,
                'stage_history' => json_encode($history),
                'started_at' => $this->ts(60), 'completed_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->bulk('courtships', $rows);

        // Counselors (demo staff accounts) + consultations + compat reports.
        $counselorUsers = [];
        for ($i = 1; $i <= 3; $i++) {
            $email = sprintf('konselor%02d@demo.jodohku.test', $i);
            $uid = DB::table('users')->insertGetId([
                'name' => 'Konselor '.$i, 'email' => $email, 'email_verified_at' => $now,
                'phone' => '+628'.str_pad((string) (9000000000 + $i), 10, '0', STR_PAD_LEFT),
                'password' => $this->passwordHash, 'account_type' => 'real', 'role' => 'member',
                'status' => 'active', 'username' => 'konselor_demo_'.$i, 'display_name' => 'Konselor '.$i,
                'date_of_birth' => now()->subYears(38)->toDateString(), 'gender' => $i % 2 ? 'male' : 'female',
                'is_verified' => true, 'is_premium' => false, 'is_demo' => true, 'is_online' => true,
                'last_active_at' => $now, 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'country' => 'Indonesia',
                'profile_completion' => 100, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $counselorUsers[] = $uid;
            DB::table('profiles')->insert(['user_id' => $uid, 'headline' => 'Konselor pranikah', 'bio' => 'Konselor demo Jodohku.', 'occupation' => 'Konselor', 'is_complete' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $counselorIds = [];
        foreach ($counselorUsers as $uid) {
            $counselorIds[] = DB::table('counselors')->insertGetId(['user_id' => $uid, 'specialty' => $this->pick(['Pranikah', 'Komunikasi', 'Keluarga']), 'bio' => 'Konselor demo.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $consults = [];
        for ($i = 0; $i < min(120, count($ids)); $i++) {
            $u = $ids[mt_rand(0, count($ids) - 1)];
            $consults[] = [
                'counselor_id' => $counselorIds[mt_rand(0, count($counselorIds) - 1)], 'user_id' => $u,
                'topic' => $this->pick(['Persiapan menikah', 'Komunikasi pasangan', 'Visi keluarga', 'Manajemen konflik']),
                'notes' => null, 'share_report' => false, 'shared_report_id' => null,
                'scheduled_at' => now()->addDays(mt_rand(1, 21))->toDateTimeString(), 'duration_minutes' => $this->pick([30, 30, 45, 60]),
                'status' => $this->pick(['pending', 'pending', 'confirmed']), 'decided_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->bulk('consultations', $consults);
        $reports = [];
        foreach ($subset as [$a, $b]) {
            $score = DB::table('match_scores')
                ->where('user_id', min($a, $b))->where('candidate_id', max($a, $b))
                ->value('total_score') ?? mt_rand(55, 95);
            $reports[] = ['user_id' => $a, 'candidate_id' => $b, 'score' => $score, 'breakdown' => json_encode(['total' => $score]), 'summary' => 'Laporan kompatibilitas demo.', 'created_at' => $now, 'updated_at' => $now];
        }
        $this->bulk('compatibility_reports', $reports);
        $this->stats['taaruf'] = count($rows);
    }

    // ---------- community ----------

    protected function seedCommunity(array $ids, array $plan): void
    {
        $now = now()->toDateTimeString();
        $groupNames = ['Pejuang Taaruf', 'Hobi Traveling', 'Kuliner Nusantara', 'Karier & Masa Depan', 'Komunitas Lari Pagi', 'Pecinta Buku', 'Fotografi HP', 'Musik & Nongkrong', 'Parenting Muda', 'Investasi Syariah', 'Masak Bareng', 'Gaming Santai'];
        $groups = [];
        for ($i = 0; $i < $plan['groups']; $i++) {
            $name = $groupNames[$i % count($groupNames)].($i >= count($groupNames) ? ' '.(intdiv($i, count($groupNames)) + 1) : '');
            $groups[] = ['owner_id' => $ids[mt_rand(0, count($ids) - 1)], 'name' => $name, 'slug' => Str::slug($name).'-demo-'.$i, 'description' => 'Grup demo: '.$name, 'visibility' => 'public', 'members_count' => 0, 'created_at' => $this->ts(150), 'updated_at' => $now];
        }
        $this->bulk('groups', $groups);
        $groupIds = DB::table('groups')->where('slug', 'like', '%-demo-%')->pluck('id')->all();
        $gm = [];
        $gmSeen = [];
        foreach ($groupIds as $gid) {
            $k = mt_rand(15, 40);
            for ($i = 0; $i < $k; $i++) {
                $u = $ids[mt_rand(0, count($ids) - 1)];
                if (isset($gmSeen[$gid.':'.$u])) {
                    continue;
                }
                $gmSeen[$gid.':'.$u] = true;
                $gm[] = ['group_id' => $gid, 'user_id' => $u, 'role' => 'member', 'joined_at' => $this->ts(120), 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->bulk('group_members', $gm);
        foreach (array_chunk($groupIds, 50) as $chunk) {
            foreach ($chunk as $gid) {
                DB::table('groups')->where('id', $gid)->update(['members_count' => DB::table('group_members')->where('group_id', $gid)->count()]);
            }
        }

        $postBodies = ['Assalamualaikum semua! Tips persiapan taaruf pertama apa ya?', 'Weekend ke mana nih yang seru buat first meet yang tetap syari?', 'Sharing dong pengalaman taaruf sampai nikah, berapa lama prosesnya?', 'Menurut kalian penting nggak sih satu frekuensi soal keuangan sebelum nikah?', ' Baru selesai baca buku pranikah, recommended banget!', 'Kopi darat komunitas minggu depan jadi kan? Absen dulu siapa yang ikut.', 'Gimana cara ngobrolin ekspektasi anak sama calon tanpa awkward?', 'Testimoni: match di sini, sekarang lagi persiapan lamaran. Doakan ya!', 'Lagi galau antara karier vs nikah muda. Ada yang pernah di posisi ini?', 'Rekomendasi tempat prewed yang affordable dong.', 'Diskusi: long distance saat taaruf, works nggak?', 'Alhamdulillah dapat insight bagus dari konselor kemarin.'];
        $posts = [];
        for ($i = 0; $i < $plan['posts']; $i++) {
            $posts[] = [
                'user_id' => $ids[mt_rand(0, count($ids) - 1)],
                'group_id' => $groupIds && $this->chance(0.6) ? $groupIds[mt_rand(0, count($groupIds) - 1)] : null,
                'body' => $this->pick($postBodies), 'visibility' => 'public',
                'likes_count' => mt_rand(0, 40), 'comments_count' => 0,
                'created_at' => $this->ts(100), 'updated_at' => $now,
            ];
        }
        $this->bulk('posts', $posts);
        $postIds = DB::table('posts')->orderByDesc('id')->limit(count($posts))->pluck('id')->all();
        $commentBodies = ['Setuju banget!', 'MasyaAllah, semoga dimudahkan.', 'Pengalaman saya mirip, kuncinya komunikasi.', 'Wah insight baru nih, makasih sharingnya.', 'Bisa dicoba pelan-pelan, jangan terburu-buru.', 'Ikut nyimak, lagi butuh info ini juga.', 'Barakallah! Semoga lancar sampai hari H.', 'Menurut saya tergantung kesiapan masing-masing sih.', 'Up! Biar makin banyak yang jawab.', 'Makasih sudah berbagi cerita.'];
        $comments = [];
        $perPost = max(1, (int) round($plan['comments'] / max(1, count($postIds))));
        foreach ($postIds as $pid) {
            $k = $perPost + mt_rand(-2, 2);
            for ($i = 0; $i < max(0, $k); $i++) {
                $comments[] = ['post_id' => $pid, 'user_id' => $ids[mt_rand(0, count($ids) - 1)], 'body' => $this->pick($commentBodies), 'created_at' => $this->ts(90), 'updated_at' => $now];
            }
        }
        $this->bulk('comments', $comments);
        foreach (array_chunk($postIds, 200) as $chunk) {
            foreach ($chunk as $pid) {
                DB::table('posts')->where('id', $pid)->update(['comments_count' => DB::table('comments')->where('post_id', $pid)->count()]);
            }
        }
        $this->stats['posts'] = count($posts);
        $this->stats['comments'] = count($comments);
        $this->stats['groups'] = count($groups);

        $eventTitles = ['Taaruf Online Bareng', 'Kopi Darat Jakarta', 'Seminar Pranikah', 'Gathering Bandung', 'Workshop Komunikasi Pasangan', 'Buka Puasa Bersama', 'Fun Run Komunitas', 'Kelas Memasak Pasangan', 'Talkshow Finansial Keluarga', 'Meetup Surabaya', 'Hiking Bareng', 'Nobar & Diskusi'];
        $events = [];
        for ($i = 0; $i < $plan['events']; $i++) {
            $title = $eventTitles[$i % count($eventTitles)].' #'.($i + 1);
            $start = now()->addDays(mt_rand(2, 90));
            $events[] = [
                'host_id' => $ids[mt_rand(0, count($ids) - 1)], 'title' => $title, 'slug' => Str::slug($title).'-demo-'.$i,
                'description' => 'Acara demo komunitas: '.$title, 'city' => $this->pick(['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Semarang']),
                'venue' => 'Gedung Serbaguna', 'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addHours(3)->toDateTimeString(),
                'capacity' => 100, 'price' => 0, 'status' => $this->pick(['published', 'published', 'ongoing']),
                'created_at' => $this->ts(60), 'updated_at' => $now,
            ];
        }
        $this->bulk('events', $events);
        $eventIds = DB::table('events')->where('slug', 'like', '%-demo-%')->pluck('id')->all();
        $em = [];
        $emSeen = [];
        foreach ($eventIds as $eid) {
            for ($i = 0, $k = mt_rand(5, 15); $i < $k; $i++) {
                $u = $ids[mt_rand(0, count($ids) - 1)];
                if (isset($emSeen[$eid.':'.$u])) {
                    continue;
                }
                $emSeen[$eid.':'.$u] = true;
                $em[] = ['event_id' => $eid, 'user_id' => $u, 'status' => 'registered', 'joined_at' => $this->ts(40), 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->bulk('event_members', $em);

        $forums = Forum::where('is_active', true)->pluck('id')->all();
        if (! $forums) {
            $forums[] = DB::table('forums')->insertGetId(['name' => 'Demo Umum', 'slug' => 'demo-umum', 'description' => 'Forum demo.', 'is_active' => true, 'sort_order' => 99, 'created_at' => $now, 'updated_at' => $now]);
        }
        $threadTitles = ['Pengalaman taaruf pertama', 'Tips foto profil yang menarik', 'Kapan waktu tepat membahas mahar?', 'Serius vs santai: mana dulu?', 'Bekal ilmu sebelum menikah', 'Cerita match terjauh', 'Etika chat dengan calon', 'Libatkan orang tua sejak kapan?', 'Bedanya taaruf dan pacaran islami', 'Persiapan finansial nikah muda'];
        $threads = [];
        for ($i = 0; $i < $plan['threads']; $i++) {
            $threads[] = [
                'forum_id' => $forums[mt_rand(0, count($forums) - 1)], 'user_id' => $ids[mt_rand(0, count($ids) - 1)],
                'title' => $this->pick($threadTitles).' ('.($i + 1).')',
                'body' => 'Thread diskusi demo. Bagaimana pendapat teman-teman soal ini?',
                'reply_count' => 0, 'last_reply_at' => null,
                'created_at' => $this->ts(100), 'updated_at' => $now,
            ];
        }
        $this->bulk('forum_threads', $threads);
        $threadIds = DB::table('forum_threads')->orderByDesc('id')->limit(count($threads))->pluck('id')->all();
        $replies = [];
        foreach ($threadIds as $tid) {
            $k = mt_rand(1, 4);
            for ($i = 0; $i < $k; $i++) {
                $replies[] = ['thread_id' => $tid, 'user_id' => $ids[mt_rand(0, count($ids) - 1)], 'body' => $this->pick($commentBodies), 'created_at' => $this->ts(80), 'updated_at' => $now];
            }
        }
        $this->bulk('forum_replies', $replies);
        foreach (array_chunk($threadIds, 200) as $chunk) {
            foreach ($chunk as $tid) {
                $c = DB::table('forum_replies')->where('thread_id', $tid)->count();
                DB::table('forum_threads')->where('id', $tid)->update(['reply_count' => $c, 'last_reply_at' => DB::table('forum_replies')->where('thread_id', $tid)->max('created_at')]);
            }
        }
        $this->stats['forums'] = count($threads);

        $blogs = [];
        $blogTitles = ['5 Bekal Sebelum Taaruf', 'Komunikasi Sehat Calon Pasangan', 'Memahami Visi Pernikahan', 'Adab Chat dengan Calon', 'Keuangan Keluarga Muda'];
        foreach ($blogTitles as $i => $t) {
            $blogs[] = [
                'user_id' => $ids[mt_rand(0, count($ids) - 1)], 'title' => $t, 'slug' => Str::slug($t).'-demo-'.$i,
                'excerpt' => 'Artikel demo: '.$t, 'body' => 'Konten artikel demo tentang '.$t.'. Ditulis untuk keperluan demonstrasi.',
                'status' => 'published', 'published_at' => $this->ts(90), 'view_count' => mt_rand(10, 500),
                'created_at' => $this->ts(90), 'updated_at' => $now,
            ];
        }
        $this->bulk('blog_posts', $blogs);
        $this->stats['events'] = count($events);
    }

    // ---------- social graph ----------

    protected function seedSocialGraph(array $ids, array $plan): void
    {
        $now = now()->toDateTimeString();
        $n = count($ids);
        if ($n < 3) {
            return;
        }
        // Follows: ~2 per user, deduped pairs, never self.
        $pairs = [];
        $target = min($n * 2, 20000);
        $guard = 0;
        while (count($pairs) < $target && $guard++ < $target * 10) {
            $a = $ids[mt_rand(0, $n - 1)];
            $b = $ids[mt_rand(0, $n - 1)];
            if ($a !== $b) {
                $pairs[$a.':'.$b] = ['follower_id' => $a, 'followed_id' => $b, 'created_at' => $this->ts(90), 'updated_at' => $now];
            }
        }
        $this->bulk('follows', array_values($pairs));
        $this->stats['follows'] = count($pairs);
        // Mutes: small sample.
        $mutes = [];
        for ($i = 0; $i < min(200, $n); $i++) {
            $a = $ids[mt_rand(0, $n - 1)];
            $b = $ids[mt_rand(0, $n - 1)];
            if ($a !== $b) {
                $mutes[$a.':'.$b] = ['muter_id' => $a, 'muted_id' => $b, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->bulk('mutes', array_values($mutes));

        $postIds = DB::table('posts')->orderByDesc('id')->limit(3000)->pluck('id')->all();
        $types = ['like', 'love', 'haha', 'wow', 'support', 'interesting'];
        $reactions = [];
        $seen = [];
        foreach ($postIds as $pid) {
            $k = mt_rand(0, 4);
            for ($i = 0; $i < $k; $i++) {
                $u = $ids[mt_rand(0, $n - 1)];
                $t = $types[mt_rand(0, count($types) - 1)];
                $key = $pid.':'.$u.':'.$t;
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $reactions[] = ['post_id' => $pid, 'user_id' => $u, 'type' => $t, 'created_at' => $this->ts(60), 'updated_at' => $now];
                }
            }
            if (count($reactions) > 8000) {
                break;
            }
        }
        $this->bulk('post_reactions', $reactions);
        $this->stats['post_reactions'] = count($reactions);
        // Bookmarks + shares.
        $bm = [];
        for ($i = 0; $i < min(1500, $n * 2); $i++) {
            $bm[$ids[mt_rand(0, $n - 1)].':'.($postIds[mt_rand(0, count($postIds) - 1)] ?? 0)] = 1;
        }
        $bmRows = [];
        foreach (array_keys($bm) as $key) {
            [$u, $p] = explode(':', $key);
            if ((int) $p > 0) {
                $bmRows[] = ['post_id' => (int) $p, 'user_id' => (int) $u, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->bulk('post_bookmarks', $bmRows);
        $shares = [];
        for ($i = 0; $i < min(400, $n); $i++) {
            $shares[] = ['post_id' => $postIds[mt_rand(0, count($postIds) - 1)], 'user_id' => $ids[mt_rand(0, $n - 1)], 'body' => null, 'created_at' => $this->ts(60), 'updated_at' => $now];
        }
        $this->bulk('post_shares', $shares);
        foreach (array_chunk(array_unique(array_column($shares, 'post_id')), 200) as $chunk) {
            foreach ($chunk as $pid) {
                DB::table('posts')->where('id', $pid)->update(['shares_count' => DB::table('post_shares')->where('post_id', $pid)->count()]);
            }
        }
        // Comment reactions sample.
        $commentIds = DB::table('comments')->orderByDesc('id')->limit(2000)->pluck('id')->all();
        $cr = [];
        $seen = [];
        foreach (array_slice($commentIds, 0, 800) as $cid) {
            $u = $ids[mt_rand(0, $n - 1)];
            $key = $cid.':'.$u;
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $cr[] = ['comment_id' => $cid, 'user_id' => $u, 'type' => 'like', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->bulk('comment_reactions', $cr);
        // Hashtags: tag a slice of posts deterministically (bulk-safe, no observers).
        $tagNames = ['taaruf', 'nikah', 'hijrah', 'kuliner', 'jakarta', 'keluarga'];
        $tagIds = [];
        foreach ($tagNames as $t) {
            // Rerunnable: demo may run twice (determinism check), so never
            // blind-insert unique slugs.
            $tagIds[$t] = DB::table('hashtags')->where('slug', $t)->value('id')
                ?? DB::table('hashtags')->insertGetId(['slug' => $t, 'name' => '#'.$t, 'posts_count' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }
        $ph = [];
        $existingPh = DB::table('post_hashtag')->pluck('hashtag_id', 'post_id')->all();
        foreach (array_slice($postIds, 0, 300) as $pid) {
            $t = $tagNames[mt_rand(0, count($tagNames) - 1)];
            if (($existingPh[$pid] ?? null) === $tagIds[$t]) {
                continue;
            }
            $ph[$pid.':'.$t] = ['post_id' => $pid, 'hashtag_id' => $tagIds[$t], 'created_at' => $now, 'updated_at' => $now];
        }
        $this->bulk('post_hashtag', array_values($ph));
        foreach ($tagIds as $tid) {
            DB::table('hashtags')->where('id', $tid)->update(['posts_count' => DB::table('post_hashtag')->where('hashtag_id', $tid)->count()]);
        }
        $this->stats['hashtags'] = count($tagIds);
        // Mentions: append @username to a slice of posts + rows.
        $usernames = DB::table('users')->whereIn('id', array_slice($ids, 0, 200))->pluck('username', 'id')->all();
        $mi = 0;
        foreach (array_slice($postIds, 300, 100) as $pid) {
            $uid = array_rand($usernames);
            $uname = $usernames[$uid];
            if (! $uname || str_contains((string) $uname, "'")) {
                continue;
            }
            DB::table('posts')->where('id', $pid)->update(['body' => DB::raw("CONCAT(body, ' @".$uname."')")]);
            DB::table('mentions')->insert([
                'mentionable_type' => 'App\\Models\\Post', 'mentionable_id' => $pid,
                'mentioned_user_id' => $uid, 'mentioned_by' => DB::table('posts')->where('id', $pid)->value('user_id'),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $mi++;
        }
        $this->stats['mentions'] = $mi;
        // Stories (text-only, no files) + views + reactions.
        $stories = [];
        for ($i = 0; $i < min(300, (int) ($n / 10)); $i++) {
            $stories[] = [
                'user_id' => $ids[mt_rand(0, $n - 1)], 'type' => 'text',
                'media_path' => null, 'body' => $this->pick(['Alhamdulillah hari ini!', 'Siap taaruf, bismillah.', 'Kopi darat yuk!', 'Belajar sabar setiap hari.', 'Weekend produktif.']),
                'visibility' => 'public', 'expires_at' => now()->addHours(mt_rand(1, 23))->toDateTimeString(),
                'views_count' => 0, 'reactions_count' => 0, 'created_at' => $this->ts(2), 'updated_at' => $now,
            ];
        }
        $this->bulk('stories', $stories);
        $storyIds = DB::table('stories')->orderByDesc('id')->limit(count($stories))->pluck('id')->all();
        $sv = [];
        $seen = [];
        foreach ($storyIds as $sid) {
            for ($i = 0, $k = mt_rand(0, 8); $i < $k; $i++) {
                $u = $ids[mt_rand(0, $n - 1)];
                $key = $sid.':'.$u;
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $sv[] = ['story_id' => $sid, 'user_id' => $u, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        $this->bulk('story_views', $sv);
        foreach (array_chunk($storyIds, 200) as $chunk) {
            foreach ($chunk as $sid) {
                DB::table('stories')->where('id', $sid)->update(['views_count' => DB::table('story_views')->where('story_id', $sid)->count()]);
            }
        }
        $this->stats['stories'] = count($stories);
        // Group posts_count + event RSVP variety.
        foreach (DB::table('groups')->where('slug', 'like', '%-demo-%')->pluck('id')->all() as $gid) {
            DB::table('groups')->where('id', $gid)->update(['posts_count' => DB::table('posts')->where('group_id', $gid)->count()]);
        }
        $emIds = DB::table('event_members')->orderBy('id')->limit(500)->pluck('id')->all();
        foreach (array_chunk($emIds, 100) as $ci => $chunk) {
            DB::table('event_members')->whereIn('id', $chunk)->update(['status' => ['confirmed', 'maybe', 'declined'][$ci % 3]]);
        }
    }

    // ---------- safety ----------

    protected function seedSafety(array $ids): void
    {
        $now = now()->toDateTimeString();
        $blocks = [];
        for ($i = 0; $i < (int) (count($ids) * 0.01); $i++) {
            $a = $ids[mt_rand(0, count($ids) - 1)];
            $b = $ids[mt_rand(0, count($ids) - 1)];
            if ($a !== $b) {
                $blocks[] = ['blocker_id' => $a, 'blocked_id' => $b, 'reason' => 'demo', 'created_at' => $this->ts(60), 'updated_at' => $now];
            }
        }
        // Dedupe pairs (unique blocker+blocked).
        $uniq = [];
        foreach ($blocks as $b) {
            $uniq[$b['blocker_id'].':'.$b['blocked_id']] = $b;
        }
        $this->bulk('blocks', array_values($uniq));
        $reports = [];
        for ($i = 0; $i < (int) (count($ids) * 0.005); $i++) {
            $a = $ids[mt_rand(0, count($ids) - 1)];
            $b = $ids[mt_rand(0, count($ids) - 1)];
            if ($a !== $b) {
                $reports[] = ['reporter_id' => $a, 'reported_user_id' => $b, 'reason' => 'other', 'details' => 'Laporan demo.', 'status' => 'pending', 'created_at' => $this->ts(45), 'updated_at' => $now];
            }
        }
        $this->bulk('reports', $reports);
        $verified = User::whereIn('id', $ids)->where('is_verified', true)->orderBy('id')->limit(500)->pluck('id');
        $verifs = [];
        foreach ($verified as $uid) {
            $verifs[] = ['user_id' => $uid, 'type' => 'id_card', 'status' => 'approved', 'notes' => 'Demo auto-approval.', 'reviewed_at' => $this->ts(50), 'created_at' => $this->ts(60), 'updated_at' => $now];
        }
        $this->bulk('verification_requests', $verifs);
    }
}
