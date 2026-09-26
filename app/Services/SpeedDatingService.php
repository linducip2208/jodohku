<?php

namespace App\Services;

use App\Models\Event;
use App\Models\SpeedDatingRound;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Speed-dating rounds for online events: confirmed attendees are paired
 * round-robin (circle method), each pair gets a direct conversation +
 * staggered start time. Like/pass reuses LikeService (mutual = match).
 * Rounds are idempotent per event (regenerating returns existing).
 */
class SpeedDatingService
{
    public function __construct(protected ChatService $chat, protected AuditService $audit) {}

    /** Generate (or return existing) rounds. Only the host may call. */
    public function generate(Event $event, User $host): array
    {
        if ((int) $event->host_id !== (int) $host->id && ! $host->isStaff()) {
            throw new \RuntimeException('Only the host can start speed dating.');
        }
        if (($event->format ?? 'meetup') !== 'speed_dating') {
            throw new \RuntimeException('Event is not speed dating.');
        }
        $existing = SpeedDatingRound::where('event_id', $event->id)->orderBy('round_no')->get();
        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        $ids = $event->members()->where('status', 'confirmed')
            ->whereHas('user', fn ($q) => $q->whereNull('users.deleted_at')->where('is_paused', false))
            ->orderBy('user_id')->limit(50)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        if (count($ids) < 2) {
            throw new \RuntimeException('Butuh minimal 2 peserta terkonfirmasi.');
        }

        return DB::transaction(function () use ($event, $host, $ids) {
            $rounds = $this->pairings($ids);
            $minutes = max(1, (int) ($event->round_minutes ?: 5));
            $base = $event->starts_at && $event->starts_at->isFuture() ? $event->starts_at : now();
            $created = [];
            foreach ($rounds as $no => $pairs) {
                foreach ($pairs as [$a, $b]) {
                    [$u1, $u2] = $a < $b ? [$a, $b] : [$b, $a];
                    $convId = null;
                    try {
                        $convId = $this->chat->findOrCreateDirect(
                            User::findOrFail($u1), User::findOrFail($u2)
                        )->id;
                    } catch (\Throwable) {
                    }
                    $created[] = SpeedDatingRound::create([
                        'event_id' => $event->id,
                        'round_no' => $no + 1,
                        'user_a_id' => $u1,
                        'user_b_id' => $u2,
                        'conversation_id' => $convId,
                        'starts_at' => (clone $base)->addMinutes($no * $minutes),
                    ]);
                }
            }
            $this->audit->log('speed_dating.generated', $host, $event, [], ['rounds' => count($created)]);

            return $created;
        });
    }

    /** Rounds for a participant with live/upcoming state (no cron needed). */
    public function roundsFor(Event $event, User $user): array
    {
        $minutes = max(1, (int) ($event->round_minutes ?: 5));
        $now = now();

        return SpeedDatingRound::where('event_id', $event->id)
            ->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->with(['partnerA:id,display_name,name,avatar_path,city', 'partnerB:id,display_name,name,avatar_path,city'])
            ->orderBy('round_no')->get()
            ->map(fn ($r) => [
                'round_no' => $r->round_no,
                'partner' => (int) $r->user_a_id === (int) $user->id ? $r->partnerB : $r->partnerA,
                'conversation_id' => $r->conversation_id,
                'starts_at' => $r->starts_at,
                'state' => $r->starts_at && $now->gte($r->starts_at) && $now->lt((clone $r->starts_at)->addMinutes($minutes)) ? 'live'
                    : ($r->starts_at && $now->gte((clone $r->starts_at)->addMinutes($minutes)) ? 'done' : 'upcoming'),
            ])->all();
    }

    /**
     * Circle-method round-robin pairings. Odd counts get a bye per round.
     *
     * @param  int[]  $ids
     * @return array<int, array<int, array{0:int,1:int}>>
     */
    public function pairings(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if (count($ids) % 2 === 1) {
            $ids[] = 0; // bye
        }
        $n = count($ids);
        $rounds = [];
        $ring = $ids;
        for ($r = 0; $r < $n - 1; $r++) {
            $pairs = [];
            for ($i = 0; $i < $n / 2; $i++) {
                $a = $ring[$i];
                $b = $ring[$n - 1 - $i];
                if ($a !== 0 && $b !== 0 && $a !== $b) {
                    $pairs[] = [$a, $b];
                }
            }
            if ($pairs !== []) {
                $rounds[] = $pairs;
            }
            // Rotate (keep first fixed).
            $ring = array_merge([$ring[0]], [$ring[$n - 1]], array_slice($ring, 1, $n - 2));
        }

        return $rounds;
    }
}
