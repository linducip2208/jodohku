<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversations.{id}', function ($user, $id) {
    $conversation = Conversation::find($id);
    if (! $conversation || ! $conversation->involves((int) $user->id)) {
        return false;
    }
    if (! empty($conversation->is_blocked)) {
        return false;
    }
    try {
        $peerId = (int) ($conversation->otherUser((int) $user->id)?->id ?? 0);
        if ($peerId && \App\Models\Block::existsBetween((int) $user->id, $peerId)) {
            return false;
        }
        if ($peerId && \App\Models\ChatBlock::where(fn ($q) => $q->where('blocker_id', $user->id)->where('blocked_id', $peerId))
            ->orWhere(fn ($q) => $q->where('blocker_id', $peerId)->where('blocked_id', $user->id))->exists()) {
            return false;
        }
    } catch (\Throwable) {
    }

    return true;
});

Broadcast::channel('online', function ($user) {
    if (! $user) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->displayName()];
});
