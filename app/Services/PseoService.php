<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Programmatic SEO taxonomy: curated location + topic pages.
 *
 * No spun content: every city/topic carries hand-written unique copy.
 * A page is indexable ONLY when it passes the quality gate (PSEO enabled
 * + enough active members for locations). Thin pages 404 — they are never
 * generated as noindex filler.
 */
class PseoService
{
    public function enabled(): bool
    {
        return (bool) app(SeoService::class)->site('pseo_enabled', true);
    }

    public function minMembers(): int
    {
        return max(1, (int) app(SeoService::class)->site('pseo_min_members', config('seo.pseo_min_members', 10)));
    }

    /** @return array<string, array{name:string,province:string,intro:string,nearby:array}> */
    public function cities(): array
    {
        return [
            'jakarta' => ['name' => 'Jakarta', 'province' => 'DKI Jakarta', 'intro' => 'Ibukota yang sibuk bukan halangan menemukan pasangan serius. Member Jakarta di Jodohku didominasi profesional muda yang mencari hubungan berkomitmen, dengan banyak acara kopi darat tiap bulan.', 'nearby' => ['tangerang', 'bekasi', 'depok']],
            'bandung' => ['name' => 'Bandung', 'province' => 'Jawa Barat', 'intro' => 'Kota kembang dengan ritme santai cocok untuk pendekatan yang tidak terburu-buru. Komunitas Bandung aktif mengadakan gathering dan diskusi pranikah rutin.', 'nearby' => ['jakarta', 'bogor', 'semarang']],
            'surabaya' => ['name' => 'Surabaya', 'province' => 'Jawa Timur', 'intro' => 'Kota pahlawan dengan etos blak-blakan — cocok untuk taaruf yang jujur sejak awal. Member Surabaya tersebar dari profesional hingga wirausaha kuliner.', 'nearby' => ['malang', 'yogyakarta', 'denpasar']],
            'yogyakarta' => ['name' => 'Yogyakarta', 'province' => 'DI Yogyakarta', 'intro' => 'Suasana Jogja yang hangat memudahkan obrolan mendalam tentang visi keluarga. Banyak member mahasiswa pascasarjana dan pekerja kreatif.', 'nearby' => ['semarang', 'surabaya', 'bandung']],
            'semarang' => ['name' => 'Semarang', 'province' => 'Jawa Tengah', 'intro' => 'Ibukota Jawa Tengah ini punya komunitas taaruf yang solid dan suportif. Cocok untukmu yang mengutamakan restu keluarga sejak awal.', 'nearby' => ['yogyakarta', 'jakarta', 'malang']],
            'medan' => ['name' => 'Medan', 'province' => 'Sumatera Utara', 'intro' => 'Keberagaman Medan tercermin di membernya — berbagai suku dan latar yang sama-sama serius mencari pasangan. Kopi darat rutin diadakan tiap bulan.', 'nearby' => ['palembang', 'jakarta', 'semarang']],
            'makassar' => ['name' => 'Makassar', 'province' => 'Sulawesi Selatan', 'intro' => 'Budaya siri na pacce membuat pendekatan di Makassar cenderung sopan dan melibatkan keluarga. Taaruf dengan wali sangat lumrah di sini.', 'nearby' => ['denpasar', 'surabaya', 'palembang']],
            'palembang' => ['name' => 'Palembang', 'province' => 'Sumatera Selatan', 'intro' => 'Member Palembang dikenal to-the-point soal keseriusan. Cocok untukmu yang ingin proses efisien tanpa drama.', 'nearby' => ['medan', 'jakarta', 'bandung']],
            'bekasi' => ['name' => 'Bekasi', 'province' => 'Jawa Barat', 'intro' => 'Kota penyangga dengan banyak pekerja komuter yang tetap menyempatkan ikhtiar jodoh. Fleksibilitas waktu jadi kunci di sini.', 'nearby' => ['jakarta', 'depok', 'bogor']],
            'tangerang' => ['name' => 'Tangerang', 'province' => 'Banten', 'intro' => 'Dekat dengan Jakarta namun ritmenya lebih tenang. Member Tangerang aktif di event gabungan Jabodetabek tiap akhir pekan.', 'nearby' => ['jakarta', 'depok', 'bogor']],
            'depok' => ['name' => 'Depok', 'province' => 'Jawa Barat', 'intro' => 'Kota pendidikan dengan banyak akademisi muda. Diskusi visi dan nilai jadi fondasi pendekatan yang kuat di Depok.', 'nearby' => ['jakarta', 'bogor', 'bekasi']],
            'bogor' => ['name' => 'Bogor', 'province' => 'Jawa Barat', 'intro' => 'Udara sejuk Bogor cocok untuk first meet yang santai di kafe kebun. Member Bogor menghargai proses yang tenang dan transparan.', 'nearby' => ['depok', 'jakarta', 'bandung']],
            'malang' => ['name' => 'Malang', 'province' => 'Jawa Timur', 'intro' => 'Kota apel dengan komunitas muda yang hangat. Banyak member yang terbuka taaruf jarak dekat maupun antarkota.', 'nearby' => ['surabaya', 'yogyakarta', 'denpasar']],
            'denpasar' => ['name' => 'Denpasar', 'province' => 'Bali', 'intro' => 'Member Denpasar memadukan keterbukaan dengan keseriusan. Cocok untuk lintas budaya yang saling menghormati.', 'nearby' => ['surabaya', 'malang', 'makassar']],
        ];
    }

    /** Aggregate active member count per city (cached, privacy-safe). */
    public function cityCounts(): array
    {
        return Cache::remember('seo:pseo:cities', 3600, function () {
            $rows = User::active()->whereNotNull('city')
                ->selectRaw('LOWER(city) as c, count(*) as total')
                ->groupBy('c')->pluck('total', 'c')->all();

            return $rows;
        });
    }

    public function cityCount(string $name): int
    {
        $counts = $this->cityCounts();

        return (int) ($counts[mb_strtolower($name)] ?? 0);
    }

    /** Indexable cities only (quality gate). */
    public function indexableCities(): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $min = $this->minMembers();

        return array_filter($this->cities(), fn ($c) => $this->cityCount($c['name']) >= $min);
    }

    /** @return array<string, array{title:string,desc:string,intro:string,points:array,faqs:array}> */
    public function topics(): array
    {
        return [
            'cara-taaruf' => [
                'title' => 'Cara Taaruf yang Benar: Panduan Lengkap 2026',
                'desc' => 'Panduan cara taaruf yang benar: niat, kriteria, proses tukaran biodata, nadhor, istikharah, hingga khitbah — dengan adab dan pendampingan wali.',
                'intro' => 'Taaruf adalah proses perkenalan menuju pernikahan yang menjaga adab: niat karena ibadah, kriteria jelas, komunikasi terarah, dan melibatkan wali sejak awal.',
                'points' => ['Luruskan niat dan tentukan kriteria pasangan yang realistis', 'Tukar biodata (cV taaruf) yang jujur: latar, visi, kesiapan', 'Nadhor: pertemuan terpantau untuk melihat kecocokan', 'Diskusikan visi, keuangan, anak, dan tempat tinggal', 'Istikharah dan libatkan orang tua sebelum khitbah'],
                'faqs' => [
                    ['q' => 'Berapa lama proses taaruf idealnya?', 'a' => 'Umumnya 1–3 bulan. Cukup untuk mengenal visi dan karakter tanpa berlarut-larut, namun tidak terburu-buru.'],
                    ['q' => 'Apakah taaruf harus lewat wali?', 'a' => 'Dianjurkan. Wali menjaga objektivitas dan adab; Jodohku mendukung peran wali/chaperone dalam proses.'],
                    ['q' => 'Bolehkan chat intens saat taaruf?', 'a' => 'Boleh selama terarah pada pengenalan (visi, nilai, kesiapan) dan menghindari rayuan. Batasi frekuensi agar tetap objektif.'],
                ],
            ],
            'tips-pasangan-serius' => [
                'title' => '7 Tips Mencari Pasangan Serius Menikah',
                'desc' => 'Tips praktis mencari pasangan serius: profil jujur, verifikasi, red flag vs green flag, dan transisi sehat dari match ke taaruf.',
                'intro' => 'Mencari pasangan serius berbeda dengan dating santai: sejak profil, filter, hingga obrolan pertama, semuanya terarah pada kesiapan menikah.',
                'points' => ['Tulis profil jujur: tujuan, nilai, dan kesiapan — bukan pencitraan', 'Prioritaskan profil terverifikasi dan lengkapi verifikasimu', 'Kenali red flag: enggan video call, minta uang, inkonsisten cerita', 'Cari green flag: konsisten, transparan, menghormati batasan', 'Pindah dari chat ke topik visi dalam 2 minggu pertama'],
                'faqs' => [
                    ['q' => 'Kapan membahas keseriusan dengan match?', 'a' => 'Sejak awal secara santun — profil dan obrolan pertama sudah bisa menyiratkan tujuan menikah.'],
                    ['q' => 'Bagaimana menolak dengan baik?', 'a' => 'Singkat, jujur, dan hormat. Unmatch tanpa drama lebih baik daripada ghosting berkepanjangan.'],
                ],
            ],
            'persiapan-pernikahan' => [
                'title' => 'Persiapan Pernikahan: Checklist Calon Pengantin',
                'desc' => 'Checklist persiapan pernikahan: kesiapan mental, finansial, kesehatan, restu keluarga, dan dokumen — plus peran konselor pranikah.',
                'intro' => 'Pernikahan yang siap bukan cuma soal gedung dan katering. Kesiapan mental, finansial, dan keselarasan visi jauh lebih menentukan.',
                'points' => ['Selaraskan visi: tempat tinggal, karier, anak, dan gaya hidup', 'Siapkan dana darurat 3–6 bulan pengeluaran sebelum menikah', 'Cek kesehatan pranikah berdua dan diskusikan hasilnya terbuka', 'Pastikan restu kedua keluarga dengan komunikasi yang baik', 'Ikuti konseling pranikah minimal 2 sesi untuk fondasi kuat'],
                'faqs' => [
                    ['q' => 'Berapa biaya nikah yang wajar?', 'a' => 'Tidak ada angka baku. Sesuaikan dengan kemampuan tanpa utang konsumtif; kesederhanaan lebih berkah.'],
                    ['q' => 'Perlukah konseling pranikah?', 'a' => 'Sangat dianjurkan — membantu menyelaraskan ekspektasi yang sering jadi sumber konflik tahun pertama.'],
                ],
            ],
            'pertanyaan-taaruf' => [
                'title' => '30 Pertanyaan Taaruf Penting Sebelum Khitbah',
                'desc' => 'Kumpulan pertanyaan taaruf seputar visi, keluarga, keuangan, anak, dan gaya hidup — plus cara menyampaikannya dengan adab.',
                'intro' => 'Pertanyaan yang tepat mencegah kejutan setelah menikah. Kelompokkan ke tema agar diskusi mengalir, bukan seperti interogasi.',
                'points' => ['Visi: gambaran 5 tahun ke depan, makna peran suami-istri', 'Keluarga: relasi dengan mertua, pola asuh, tinggal dengan orang tua?', 'Keuangan: pengelolaan gaji, utang, target finansial bersama', 'Anak: keinginan, jumlah, jarak usia, dan pola pengasuhan', 'Gaya hidup: karier istri, mobilitas, dan cara menyelesaikan konflik'],
                'faqs' => [
                    ['q' => 'Bagaimana menanyakan hal sensitif?', 'a' => 'Awali dengan konteks ("agar kita selaras..."), dengarkan tanpa menghakimi, dan catat kesepakatannya.'],
                    ['q' => 'Bolehkah bertanya soal masa lalu?', 'a' => 'Fokus pada pelajaran dan kondisi saat ini, bukan detail yang tidak relevan. Hormati batasan yang disampaikan.'],
                ],
            ],
            'komunikasi-pasangan' => [
                'title' => 'Komunikasi Pasangan Sehat: Fondasi Pernikahan',
                'desc' => 'Panduan komunikasi pasangan: mendengar aktif, menyampaikan kebutuhan tanpa menyalahkan, dan ritual check-in rutin.',
                'intro' => 'Mayoritas konflik rumah tangga berakar pada komunikasi, bukan pada masalahnya sendiri. Kabar baiknya: komunikasi bisa dilatih sejak masa taaruf.',
                'points' => ['Gunakan kalimat "aku" bukan "kamu": sampaikan rasa, bukan tuduhan', 'Dengarkan sampai selesai sebelum merespons atau membela diri', 'Sepakati waktu check-in mingguan untuk membahas hal mengganjal', 'Bedakan masalah yang bisa dikompromikan dan nilai yang tidak', 'Minta maaf spesifik dan perbaiki perilaku, bukan sekadar kata-kata'],
                'faqs' => [
                    ['q' => 'Bagaimana menghadapi pasangan yang defensif?', 'a' => 'Pilih momen tenang, apresiasi dulu, lalu sampaikan satu isu dalam satu waktu.'],
                    ['q' => 'Kapan butuh konselor?', 'a' => 'Saat konflik berulang dengan pola sama, atau komunikasi sudah dipenuhi bentakan/diam berkepanjangan.'],
                ],
            ],
        ];
    }

    /** Breadcrumb trail helper for PSEO pages. */
    public function crumbs(array $tail): array
    {
        $base = rtrim((string) config('app.url', url('/')), '/');
        $out = [['name' => 'Beranda', 'url' => $base.'/']];
        foreach ($tail as $c) {
            $out[] = $c;
        }

        return $out;
    }
}
