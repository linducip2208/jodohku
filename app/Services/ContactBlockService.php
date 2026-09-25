<?php

namespace App\Services;

use App\Models\Block;
use App\Models\ContactHash;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Privacy-preserving contact blocking. Raw contact books are NEVER
 * uploaded or stored: the client hashes normalized numbers locally (or
 * the user pastes numbers that are hashed server-side and never
 * persisted raw). Matches resolve into regular Block rows (reason
 * contact), so discovery/feed/search respect them automatically.
 */
class ContactBlockService
{
    /**
     * Normalize an Indonesian (default) phone number to digits.
     * 0812… → 62812…, +62… → 62…, 62… stays. Null when unusable.
     */
    public static function normalizePhone(string $raw, string $defaultCountry = 'ID'): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $digits = ltrim($digits, '0');
        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D+/', '', $raw) ?? '';
        } elseif ($defaultCountry === 'ID' && ! str_starts_with($digits, '62')) {
            // Local number without country code (leading 0 already stripped).
            if (strlen($digits) >= 9 && strlen($digits) <= 12) {
                $digits = '62'.$digits;
            } else {
                return null;
            }
        }
        if (strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * @param  string[]  $phones  raw numbers from the user
     * @return array{imported:int, matched:int}
     */
    public function import(User $user, array $phones): array
    {
        $hashes = [];
        foreach (array_slice($phones, 0, 1000) as $raw) {
            $normalized = self::normalizePhone((string) $raw);
            if ($normalized !== null) {
                $hashes[ContactHash::hash($normalized)] = true;
            }
        }
        if (! $hashes) {
            return ['imported' => 0, 'matched' => 0];
        }

        return DB::transaction(function () use ($user, $hashes) {
            foreach (array_keys($hashes) as $hash) {
                ContactHash::firstOrCreate(['user_id' => $user->id, 'phone_hash' => $hash]);
            }
            // Resolve against registered phone hashes (never raw numbers).
            $matchedIds = User::whereIn('phone_hash', array_keys($hashes))
                ->where('id', '!=', $user->id)->pluck('id')->all();
            foreach ($matchedIds as $id) {
                Block::firstOrCreate(
                    ['blocker_id' => $user->id, 'blocked_id' => $id],
                    ['reason' => 'contact']
                );
            }

            return ['imported' => count($hashes), 'matched' => count($matchedIds)];
        });
    }

    /** Opt-out: delete hashes + contact-origin blocks. Raw numbers never existed. */
    public function removeAll(User $user): void
    {
        DB::transaction(function () use ($user) {
            ContactHash::where('user_id', $user->id)->delete();
            Block::where('blocker_id', $user->id)->where('reason', 'contact')->delete();
        });
    }

    public function status(User $user): array
    {
        return [
            'hashes' => ContactHash::where('user_id', $user->id)->count(),
            'matched' => Block::where('blocker_id', $user->id)->where('reason', 'contact')->count(),
        ];
    }
}
