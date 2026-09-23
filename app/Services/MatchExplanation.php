<?php

namespace App\Services;

use App\Models\User;

/**
 * Presentation layer over MatchingEngine scores (NOT a second engine).
 *
 * Turns scorePair()/breakdown into a stable, Indonesian, privacy-safe
 * structure shared by Discover, Profile, Likes, Matches, Taaruf, the API,
 * and the AI matchmaker. Only facts both members may already see are used:
 * shared interest names, same city/goal/occupation/education, age
 * compatibility, questionnaire similarity (never answer details), and
 * per-dimension scores. No emails, phones, locations precis, preferences
 * internals, or moderation data ever enter reasons.
 */
class MatchExplanation
{
    protected array $dimensionLabels = [
        'age' => 'Usia',
        'location' => 'Lokasi',
        'preference' => 'Preferensi',
        'personality' => 'Kepribadian',
        'interest' => 'Minat',
        'lifestyle' => 'Gaya Hidup',
        'goal' => 'Tujuan',
        'behavior' => 'Aktivitas',
    ];

    public function __construct(protected MatchingEngine $engine) {}

    /**
     * @return array{overall:float, strength:string, dimensions:array, reasons:array, cautions:array, shared_interests:array}
     */
    public function for(User $a, User $b): array
    {
        $r = $this->engine->scorePair($a, $b);
        $a->loadMissing(['profile', 'interests']);
        $b->loadMissing(['profile', 'interests']);

        $dimensions = [];
        foreach ($this->dimensionLabels as $key => $label) {
            $dimensions[] = [
                'key' => $key,
                'label' => $label,
                'score' => (float) ($r['breakdown'][$key] ?? 0),
                'weight' => (float) ($r['weights'][$key] ?? 0),
            ];
        }

        $reasons = [];
        $cautions = [];
        $shared = $a->interests->pluck('name')->intersect($b->interests->pluck('name'))->values()->all();
        if ($shared) {
            $reasons[] = count($shared).' minat sama: '.implode(', ', array_slice($shared, 0, 4));
        }
        $ga = $a->profile?->relationship_goal;
        $gb = $b->profile?->relationship_goal;
        $gaV = $ga?->value ?? (string) $ga;
        $gbV = $gb?->value ?? (string) $gb;
        if ($gaV && $gbV) {
            if ($gaV === $gbV) {
                $reasons[] = 'Sama-sama mencari '.str_replace('_', ' ', $gaV);
            } else {
                $cautions[] = 'Tujuan berbeda: '.str_replace('_', ' ', $gaV).' vs '.str_replace('_', ' ', $gbV);
            }
        }
        if ($a->city && $b->city) {
            if (strtolower($a->city) === strtolower($b->city)) {
                $reasons[] = 'Sama-sama di '.$a->city;
            } else {
                $cautions[] = 'Beda kota: '.$a->city.' vs '.$b->city;
            }
        }
        if (($r['breakdown']['personality'] ?? 0) >= 70) {
            $reasons[] = 'Jawaban kuesioner kalian selaras';
        }
        if (($r['breakdown']['preference'] ?? 0) >= 70) {
            $reasons[] = 'Saling memenuhi kriteria pasangan';
        }
        $pref = $a->partnerPreference;
        if ($pref && $pref->min_age && $pref->max_age && ($bAge = $b->age())) {
            if ($bAge >= $pref->min_age && $bAge <= $pref->max_age) {
                $reasons[] = 'Usia masuk rentang yang dicari';
            }
        }
        if ($a->is_online && $b->is_online) {
            $reasons[] = 'Sama-sama sedang aktif';
        }

        $overall = (float) $r['mutual'];

        return [
            'overall' => $overall,
            'strength' => $overall >= 80 ? 'Sangat Cocok' : ($overall >= 60 ? 'Cocok' : ($overall >= 40 ? 'Cukup Cocok' : 'Kurang Cocok')),
            'dimensions' => $dimensions,
            'reasons' => array_values($reasons),
            'cautions' => array_values($cautions),
            'shared_interests' => array_values($shared),
        ];
    }
}
