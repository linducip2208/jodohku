<?php

namespace App\Livewire;

use Livewire\Component;

class ProfileCompleteness extends Component
{
    public function render()
    {
        $u = auth()->user();
        $pct = (int) ($u->profile_completion ?? 0);
        if ($u && $u->relationLoaded('profile') === false) { try { $u->loadMissing('profile'); } catch (\Throwable) {} }
        if ($u && $u->profile) { try { $pct = max($pct, $u->profile->completenessScore()); } catch (\Throwable) {} }
        $tips = [];
        if ($u) {
            if (empty($u->avatar_path)) $tips[] = 'Tambahkan foto profil';
            if (empty($u->profile?->bio)) $tips[] = 'Tulis bio menarik';
            if (empty($u->profile?->occupation)) $tips[] = 'Isi pekerjaan';
            if (($u->interests()->count() ?? 0) === 0) $tips[] = 'Pilih minimal 3 minat';
        }
        return view('livewire.profile-completeness', ['pct' => $pct, 'tips' => $tips]);
    }
}
