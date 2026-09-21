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

    return $conversation && $conversation->involves((int) $user->id);
});

Broadcast::channel('online', function ($user) {
    if (! $user) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->displayName()];
});
