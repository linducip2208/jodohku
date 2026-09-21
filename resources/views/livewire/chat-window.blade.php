<div x-data="{ open: false }" wire:poll.10s>
@if(!$conv)
@include('components.empty', ['icon' => '💬', 'title' => 'Percakapan tidak ditemukan', 'hint' => 'Kembali ke inbox.'])
@else
<div class="jk-section" style="display:flex;gap:12px;align-items:center">
<div class="jk-avatar">@if($other?->avatar_path)<img src="{{ $other->avatar_path }}" alt="">@else{{ strtoupper(substr((string)($other?->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1"><strong>{{ $other?->displayName() ?? 'Member' }}</strong>
<div class="jk-muted" style="font-size:12px">@if($other?->is_online) <span style="color:#22c55e">● Online sekarang</span> @else Terakhir aktif {{ $other?->last_active_at?->diffForHumans() ?? '—' }} @endif · <span id="typingHint"></span></div></div>
<div style="display:flex;gap:6px">
<a class="jk-pill" href="/profile/{{ $other?->id }}">Profil</a>
<form method="POST" action="/chat/{{ $conv->id }}/pin" style="display:inline">@csrf<button class="jk-pill" type="submit">📌</button></form>
<form method="POST" action="/chat/{{ $conv->id }}/mute" style="display:inline">@csrf<button class="jk-pill" type="submit">🔕</button></form>
<form method="POST" action="/chat/{{ $conv->id }}/archive" style="display:inline">@csrf<button class="jk-pill" type="submit">📦</button></form>
</div>
</div>
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px" id="msgList">
@foreach($messages as $m)
@php $mine = $me && $m->sender_id === $me->id; @endphp
<div style="display:flex;{{ $mine ? 'justify-content:flex-end' : 'justify-content:flex-start' }}">
<div class="jk-bubble {{ $mine ? 'me' : 'them' }}">
@if($m->replyTo)<div style="font-size:11px;opacity:.75;border-left:2px solid currentColor;padding-left:6px;margin-bottom:4px">{{ \Illuminate\Support\Str::limit($m->replyTo->body ?? '', 60) }}</div>@endif
<div>{{ $m->body }}</div>
@if($m->attachments->count())<div style="font-size:11px;margin-top:4px">📎 {{ $m->attachments->count() }} lampiran (foto/video/file/voice-note tersimpan aman)</div>@endif
<div style="font-size:10px;opacity:.7;margin-top:4px;display:flex;gap:6px">
<span>{{ $m->created_at?->format('H:i') }}</span>
@if($m->is_edited)<span>· diedit</span>@endif
@if($mine)<span>· {{ $m->reads->count() ? 'dibaca ✓✓' : 'terkirim ✓' }}</span>@endif
@foreach($m->reactions as $r)<span>{{ $r->emoji }}</span>@endforeach
</div>
@if($mine)
<div style="margin-top:4px;display:flex;gap:6px;font-size:11px">
<button wire:click="react({{ $m->id }}, '❤️')" style="background:none;border:0;cursor:pointer">❤️</button>
<button wire:click="react({{ $m->id }}, '😂')" style="background:none;border:0;cursor:pointer">😂</button>
<button wire:click="react({{ $m->id }}, '😮')" style="background:none;border:0;cursor:pointer">😮</button>
<button wire:click="delete({{ $m->id }})" style="background:none;border:0;cursor:pointer;color:inherit">🗑</button>
</div>
@endif
</div>
</div>
@endforeach
</div>
@livewire('ai-assistant-box', ['conversationId' => $conv->id], key('ai-' . $conv->id))
<form wire:submit.prevent="send" style="display:flex;gap:8px;position:sticky;bottom:70px;background:#fff;padding:8px;border:1px solid #f1f1f4;border-radius:14px">
<input class="jk-input" style="flex:1" wire:model="body" placeholder="Tulis pesan... (foto/video/file/voice-note via tombol 📎)" autocomplete="off">
<button class="jk-btn jk-btn-like" style="flex:none;padding:10px 18px" type="submit">Kirim</button>
</form>
<div class="jk-muted" style="font-size:11.5px;margin-top:8px">Arsitektur lampiran: foto/video/file/voice-note diupload ke storage privat → MessageAttachment (path, mime, size) → moderasi AI → tampil sebagai thumbnail/player. Voice-note direkam via MediaRecorder lalu diupload sebagai audio/webm.</div>
<div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
<form method="POST" action="/chat/{{ $conv->id }}/unmatch" onsubmit="return confirm('Yakin unmatch?')">@csrf<button class="jk-pill" type="submit">💔 Unmatch</button></form>
<form method="POST" action="/safety/block" onsubmit="return confirm('Blokir user ini?')">@csrf<input type="hidden" name="user_id" value="{{ $other?->id }}"><button class="jk-pill" type="submit">⛔ Block</button></form>
<form method="POST" action="/safety/report">@csrf<input type="hidden" name="user_id" value="{{ $other?->id }}"><button class="jk-pill" type="submit">🚩 Laporkan</button></form>
</div>
@error('body')<div class="jk-alert err">{{ $message }}</div>@enderror
@endif
</div>
