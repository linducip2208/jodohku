<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\ChatService;
use Livewire\Component;

class ChatInbox extends Component
{
    public string $filter = 'all';
    public string $search = '';

    protected $queryString = ['filter', 'search'];

    public function setFilter(string $f): void { $this->filter = $f; }

    public function render(ChatService $chat)
    {
        $user = auth()->user();
        $items = collect();
        if ($user) {
            try {
                $q = Conversation::forUser($user->id)->with(['users', 'latestMessages'])->latest('last_message_at')->take(40)->get();
                if ($this->search !== '') {
                    $s = mb_strtolower($this->search);
                    $q = $q->filter(fn ($c) => str_contains(mb_strtolower($c->title ?? $c->otherUser($user->id)?->displayName() ?? ''), $s));
                }
                if ($this->filter === 'unread') {
                    $q = $q->filter(fn ($c) => $chat->unreadCount($c, $user) > 0);
                } elseif ($this->filter === 'online') {
                    $q = $q->filter(fn ($c) => (bool) $c->otherUser($user->id)?->is_online);
                } elseif ($this->filter === 'verified') {
                    $q = $q->filter(fn ($c) => (bool) $c->otherUser($user->id)?->is_verified);
                } elseif ($this->filter === 'premium') {
                    $q = $q->filter(fn ($c) => (bool) $c->otherUser($user->id)?->is_premium);
                } elseif ($this->filter === 'archived') {
                    $q = $q->filter(fn ($c) => (bool) $c->is_archived);
                }
                $items = $q->values();
            } catch (\Throwable) { $items = collect(); }
        }
        return view('livewire.chat-inbox', ['items' => $items]);
    }
}
