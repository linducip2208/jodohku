<?php

namespace App\Services;

/**
 * Single source of truth for public FAQs (GEO/AI-readable content).
 *
 * The same items render visibly (faq-list component) and feed FAQPage
 * JSON-LD — never hidden-from-user crawler-only content.
 */
class FaqService
{
    /** @return array<int, array{q:string,a:string}> */
    public function homepage(): array
    {
        return [
            ['q' => 'Apakah Jodohku gratis?', 'a' => 'Ya. Akun gratis dapat 20 like per hari, chat dengan match, dan ikut event publik. Premium membuka filter lanjutan dan like tanpa batas.'],
            ['q' => 'Bagaimana skor kompatibilitas dihitung?', 'a' => 'Delapan dimensi: usia, lokasi, preferensi, kepribadian (kuesioner), minat, gaya hidup, tujuan hubungan, dan perilaku. Lihat penjelasan “Kenapa cocok?” di tiap profil.'],
            ['q' => 'Apakah profil terverifikasi aman?', 'a' => 'Profil dengan badge biru telah lolos verifikasi foto/dokumen. Kamu juga bisa blokir, laporkan, dan aktifkan mode incognito.'],
            ['q' => 'Apakah tersedia voice/video call?', 'a' => 'Voice note, foto, dan file sudah didukung di chat. Panggilan suara dan video tersedia untuk percakapan yang memenuhi syarat.'],
            ['q' => 'Bagaimana cara membatalkan Premium?', 'a' => 'Buka Pengaturan → Langganan → Batalkan. Akses premium tetap aktif hingga akhir periode.'],
        ];
    }

    /** @return array<int, array{q:string,a:string}> */
    public function safety(): array
    {
        return [
            ['q' => 'Bagaimana melaporkan member mencurigakan?', 'a' => 'Buka profil atau chat-nya, tekan Laporkan, pilih alasan. Moderator manusia meninjau setiap laporan.'],
            ['q' => 'Apakah memblokir member menghapus riwayat chat?', 'a' => 'Blokir menghentikan semua interaksi baru dan menyembunyikan profil kalian satu sama lain.'],
            ['q' => 'Tips kopi darat yang aman?', 'a' => 'Pilih tempat umum dan ramai, beri tahu keluarga/teman lokasimu, datang dan pulang sendiri.'],
        ];
    }
}
