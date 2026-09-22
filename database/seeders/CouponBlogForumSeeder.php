<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Coupon;
use App\Models\Forum;
use App\Models\User;
use Illuminate\Database\Seeder;

class CouponBlogForumSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::firstOrCreate(['code' => 'WELCOME10'], [
            'name' => 'Diskon 10% member baru',
            'type' => 'percent',
            'value' => 10,
            'max_discount' => 20000,
            'min_order' => 29000,
            'per_user_limit' => 1,
            'is_active' => true,
        ]);

        Coupon::firstOrCreate(['code' => 'HEMAT25K'], [
            'name' => 'Potongan Rp25rb premium tahunan',
            'type' => 'fixed',
            'value' => 25000,
            'min_order' => 100000,
            'usage_limit' => 500,
            'per_user_limit' => 1,
            'is_active' => true,
        ]);

        BlogPost::firstOrCreate(['slug' => 'pembuka-obrolan-pertama'], [
            'title' => '10 Pembuka Obrolan Pertama yang Sopan',
            'excerpt' => 'Ice-breaker yang terbukti dibalas tanpa terkesan aneh.',
            'body' => "1. Sapa + sebutkan sesuatu dari profilnya.\n2. Tanyakan hal ringan yang mudah dijawab.\n3. Hindari menanyakan kontak pribadi di awal.\n\nContoh: \"Halo! Aku lihat kamu suka hiking — gunung favoritmu di mana?\"",
            'status' => 'published',
            'published_at' => now()->subDays(6),
        ]);

        BlogPost::firstOrCreate(['slug' => 'checklist-siap-nikah'], [
            'title' => 'Checklist Siap Nikah ala Biro Jodoh',
            'excerpt' => 'Persiapan mental, finansial, dan restu keluarga.',
            'body' => "1. Visi pernikahan selaras.\n2. Keterbukaan finansial.\n3. Kenalkan keluarga di waktu tepat.\n4. Sepakati peran & ekspektasi.\n\nKonselor Jodohku siap mendampingi via Premium.",
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);

        $forum = Forum::firstOrCreate(['slug' => 'persiapan-nikah'], [
            'name' => 'Persiapan Nikah',
            'description' => 'Diskusi persiapan menuju pernikahan serius.',
            'sort_order' => 1,
        ]);
        Forum::firstOrCreate(['slug' => 'kenalan-pertama'], [
            'name' => 'Kenalan Pertama',
            'description' => 'Cerita & tips pendekatan pertama yang aman.',
            'sort_order' => 2,
        ]);

        if ($forum->threads()->count() === 0 && ($admin = User::where('role', 'superadmin')->first())) {
            $thread = $forum->threads()->create([
                'user_id' => $admin->id,
                'title' => 'Selamat datang — perkenalkan dirimu di sini',
                'body' => 'Tulis kota, hobi, dan pasangan seperti apa yang kamu cari. Jaga etika, dilarang membagikan kontak pribadi.',
                'is_pinned' => true,
                'last_reply_at' => now(),
            ]);
            $thread->replies()->create([
                'user_id' => $admin->id,
                'body' => 'Contoh: Halo! Aku Rani dari Bandung, suka masak & lari pagi. Mencari pasangan serius usia 28-35. Salam kenal!',
            ]);
            $thread->update(['reply_count' => 1]);
        }
    }
}
