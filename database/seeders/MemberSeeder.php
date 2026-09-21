<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Interest;
use App\Models\NotificationPreference;
use App\Models\PartnerPreference;
use App\Models\Profile;
use App\Models\ProfilePhoto;
use App\Models\ProfilePrivacy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        // Explicitly fictional Indonesian persons (seed data only).
        $members = [
            ['name' => 'Andi Pratama', 'email' => 'andi.pratama@example.test', 'gender' => Gender::Male, 'dob' => '1995-03-12', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'occupation' => 'Software Engineer', 'goal' => 'marriage'],
            ['name' => 'Siti Rahayu', 'email' => 'siti.rahayu@example.test', 'gender' => Gender::Female, 'dob' => '1997-07-21', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'occupation' => 'Guru', 'goal' => 'serious_relationship'],
            ['name' => 'Budi Santoso', 'email' => 'budi.santoso@example.test', 'gender' => Gender::Male, 'dob' => '1992-11-02', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'occupation' => 'Wirausaha Kuliner', 'goal' => 'marriage'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi.lestari@example.test', 'gender' => Gender::Female, 'dob' => '1996-01-30', 'city' => 'Semarang', 'province' => 'Jawa Tengah', 'occupation' => 'Perawat', 'goal' => 'dating'],
            ['name' => 'Rizky Ramadhan', 'email' => 'rizky.ramadhan@example.test', 'gender' => Gender::Male, 'dob' => '1994-05-17', 'city' => 'Medan', 'province' => 'Sumatera Utara', 'occupation' => 'Desainer Grafis', 'goal' => 'serious_relationship'],
            ['name' => 'Putri Ayu', 'email' => 'putri.ayu@example.test', 'gender' => Gender::Female, 'dob' => '1998-09-08', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'occupation' => 'Marketing', 'goal' => 'marriage'],
            ['name' => 'Agus Wijaya', 'email' => 'agus.wijaya@example.test', 'gender' => Gender::Male, 'dob' => '1990-12-25', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'occupation' => 'Akuntan', 'goal' => 'dating'],
            ['name' => 'Maya Kusuma', 'email' => 'maya.kusuma@example.test', 'gender' => Gender::Female, 'dob' => '1999-04-14', 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'occupation' => 'Mahasiswa Pascasarjana', 'goal' => 'friendship'],
            ['name' => 'Fajar Nugroho', 'email' => 'fajar.nugroho@example.test', 'gender' => Gender::Male, 'dob' => '1993-08-19', 'city' => 'Semarang', 'province' => 'Jawa Tengah', 'occupation' => 'Fotografer', 'goal' => 'serious_relationship'],
            ['name' => 'Intan Permata', 'email' => 'intan.permata@example.test', 'gender' => Gender::Female, 'dob' => '1995-06-06', 'city' => 'Medan', 'province' => 'Sumatera Utara', 'occupation' => 'Dokter Muda', 'goal' => 'marriage'],
            ['name' => 'Hendra Gunawan', 'email' => 'hendra.gunawan@example.test', 'gender' => Gender::Male, 'dob' => '1991-02-11', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'occupation' => 'Pilot', 'goal' => 'marriage'],
            ['name' => 'Rina Marlina', 'email' => 'rina.marlina@example.test', 'gender' => Gender::Female, 'dob' => '1997-10-03', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'occupation' => 'Arsitek', 'goal' => 'dating'],
        ];

        $interestIds = Interest::pluck('id')->all();

        foreach ($members as $idx => $m) {
            $user = User::firstOrCreate(
                ['email' => $m['email']],
                [
                    'name' => $m['name'],
                    'display_name' => explode(' ', $m['name'])[0],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'account_type' => AccountType::Real,
                    'role' => UserRole::Member,
                    'status' => UserStatus::Active,
                    'date_of_birth' => $m['dob'],
                    'gender' => $m['gender'],
                    'city' => $m['city'],
                    'province' => $m['province'],
                    'country' => 'Indonesia',
                    'latitude' => -6.2 + ($idx * 0.01),
                    'longitude' => 106.8 + ($idx * 0.01),
                    'is_verified' => $idx % 3 === 0,
                    'profile_completion' => 85,
                ]
            );

            Profile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'headline' => 'Halo! Saya '.$m['name'].', senang berkenalan.',
                    'bio' => 'Profil fiktif untuk seed. Saya suka kuliner, traveling, dan ngobrol santai tentang masa depan.',
                    'occupation' => $m['occupation'],
                    'education' => 'S1',
                    'religion' => 'Islam',
                    'marital_status' => 'single',
                    'relationship_goal' => $m['goal'],
                    'languages' => 'Indonesia, English',
                    'is_complete' => true,
                ]
            );

            ProfilePrivacy::firstOrCreate(['user_id' => $user->id], []);

            ProfilePhoto::firstOrCreate(
                ['user_id' => $user->id, 'path' => 'placeholders/members/'.strtolower(str_replace(' ', '-', $m['name'])).'.jpg'],
                ['sort_order' => 0, 'is_primary' => true, 'is_approved' => true, 'status' => 'approved', 'width' => 800, 'height' => 800]
            );

            PartnerPreference::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'min_age' => 22, 'max_age' => 35,
                    'gender_preference' => $m['gender'] === Gender::Male ? 'female' : 'male',
                    'max_distance_km' => 200,
                    'relationship_goal' => $m['goal'],
                    'verified_only' => false, 'photo_only' => false,
                ]
            );

            NotificationPreference::firstOrCreate(['user_id' => $user->id], []);

            if ($interestIds) {
                $pick = array_slice($interestIds, $idx % count($interestIds), 3);
                if (count($pick) < 3) {
                    $pick = array_merge($pick, array_slice($interestIds, 0, 3 - count($pick)));
                }
                $user->interests()->syncWithoutDetaching($pick);
            }
        }
    }
}
