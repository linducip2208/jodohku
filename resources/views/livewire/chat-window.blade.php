<div x-data="{ open: false }" wire:poll.10s>
@if(!$conv)
@include('components.empty', ['icon' => '💬', 'title' => 'Percakapan tidak ditemukan', 'hint' => 'Kembali ke inbox.'])
@else
<div class="jk-section" style="display:flex;gap:12px;align-items:center">
<div class="jk-avatar">@if($other?->avatarUrl())<img src="{{ $other->avatarUrl() }}" alt="Foto {{ $other->displayName() }}">@else{{ strtoupper(substr((string)($other?->displayName() ?? '?'),0,1)) }}@endif</div>
<div style="flex:1"><strong>{{ $other?->displayName() ?? 'Member' }}</strong>@if(!empty($courtshipStage)) <span class="jk-pill" style="font-size:11px" title="Tahap taaruf berjalan">Taaruf: {{ $courtshipStage }}</span>@endif
<div class="jk-muted" style="font-size:12px">@if($other?->is_online) <span style="color:#22c55e">● Online sekarang</span> @else Terakhir aktif {{ $other?->last_active_at?->diffForHumans() ?? '—' }} @endif · <span id="typingHint"></span></div></div>
<div style="display:flex;gap:6px">
<a class="jk-pill" href="/profile/{{ $other?->id }}">Profil</a>
<form method="POST" action="/chat/{{ $conv->id }}/pin" style="display:inline">@csrf<button class="jk-pill" type="submit" title="Sematkan">Pin</button></form>
<form method="POST" action="/chat/{{ $conv->id }}/mute" style="display:inline">@csrf<button class="jk-pill" type="submit" title="Bisukan">Bisukan</button></form>
<form method="POST" action="/chat/{{ $conv->id }}/archive" style="display:inline">@csrf<button class="jk-pill" type="submit" title="Arsipkan">Arsip</button></form>
</div>
</div>
@if(in_array($peerRisk ?? null, ['medium', 'high'], true))
<div class="jk-alert err" role="alert">Perhatikan keamanan saat berkomunikasi. Jangan kirim uang atau kode OTP kepada orang lain.</div>
@endif
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px" id="msgList">
@foreach($messages as $m)
@php $mine = $me && $m->sender_id === $me->id; @endphp
<div style="display:flex;{{ $mine ? 'justify-content:flex-end' : 'justify-content:flex-start' }}">
<div class="jk-bubble {{ $mine ? 'me' : 'them' }}">
@if($m->replyTo)<div style="font-size:11px;opacity:.75;border-left:2px solid currentColor;padding-left:6px;margin-bottom:4px">{{ \Illuminate\Support\Str::limit($m->replyTo->body ?? '', 60) }}</div>@endif
<div>{{ $m->body }}</div>
@if($m->attachments->count())
@foreach($m->attachments as $att)
@if(str_starts_with($att->mime_type ?? '', 'image/'))
<div style="margin-top:6px"><img src="{{ route('member.chat.attachment.download', $att) }}" alt="Lampiran foto" style="max-width:220px;border-radius:12px" loading="lazy"></div>
@elseif(str_starts_with($att->mime_type ?? '', 'video/'))
<div style="margin-top:6px"><video src="{{ route('member.chat.attachment.download', $att) }}" controls style="max-width:220px;border-radius:12px"></video></div>
@elseif(str_starts_with($att->mime_type ?? '', 'audio/'))
<div style="margin-top:6px"><audio src="{{ route('member.chat.attachment.download', $att) }}" controls style="max-width:220px"></audio></div>
@else
<div style="font-size:12px;margin-top:6px"><a href="{{ route('member.chat.attachment.download', $att) }}" target="_blank" rel="noopener">{{ $att->file_name ?? 'Unduh lampiran' }}</a></div>
@endif
@endforeach
@endif
<div style="font-size:10px;opacity:.7;margin-top:4px;display:flex;gap:6px">
<span>{{ $m->created_at?->format('H:i') }}</span>
@if($m->is_edited)<span>· diedit</span>@endif
@if($mine)<span>· {{ ($canSeeReads ?? false) && $m->reads->count() ? 'dibaca ✓✓' : 'terkirim ✓' }}</span>@endif
@foreach($m->reactions as $r)<span>{{ $r->emoji }}</span>@endforeach
</div>
@if($mine)
<div style="margin-top:4px;display:flex;gap:6px;font-size:11px">
<button wire:click="react({{ $m->id }}, '❤️')" style="background:none;border:0;cursor:pointer" aria-label="Suka">❤️</button>
<button wire:click="react({{ $m->id }}, '😂')" style="background:none;border:0;cursor:pointer" aria-label="Tertawa">😂</button>
<button wire:click="react({{ $m->id }}, '😮')" style="background:none;border:0;cursor:pointer" aria-label="Kagum">😮</button>
<button wire:click="delete({{ $m->id }})" style="background:none;border:0;cursor:pointer;color:inherit" aria-label="Hapus pesan">Hapus</button>
</div>
@endif
</div>
</div>
@endforeach
</div>
@livewire('ai-assistant-box', ['conversationId' => $conv->id], key('ai-' . $conv->id))
<form action="/chat/{{ $conv->id }}/attachments" method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
@csrf
<label class="jk-pill" style="cursor:pointer" title="Kirim foto, video, voice note, atau file">Lampiran
<input type="file" name="file" accept="image/*,video/*,audio/*,.pdf" style="display:none" onchange="this.form.submit()">
</label>
<span class="jk-muted" style="font-size:11.5px">Foto, video, voice note &amp; PDF (maks 50MB video, 25MB audio)</span>
</form>
<form wire:submit.prevent="send" style="display:flex;gap:8px;position:sticky;bottom:70px;background:#fff;padding:8px;border:1px solid #f1f1f4;border-radius:14px">
<input class="jk-input" style="flex:1" wire:model="body" placeholder="Tulis pesan..." autocomplete="off" aria-label="Tulis pesan">
<button class="jk-btn jk-btn-like" style="flex:none;padding:10px 18px" type="submit">Kirim</button>
</form>
<form wire:submit.prevent="sendGift" style="display:flex;gap:8px;align-items:center;margin-top:8px">
<select class="jk-input" style="flex:1" wire:model="giftCode" aria-label="Pilih gift">
<option value="">Kirim gift…</option>
@foreach(($giftCatalog ?? []) as $gift)
<option value="{{ $gift->code }}">{{ $gift->name }} ({{ $gift->credit_price }} kredit)</option>
@endforeach
</select>
<button class="jk-pill" style="flex:none" type="submit">Kirim Gift</button>
</form>
@error('giftCode')<div class="jk-alert err">{{ $message }}</div>@enderror
<div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
<form method="POST" action="/chat/{{ $conv->id }}/unmatch" onsubmit="return confirm('Yakin unmatch? Percakapan akan diarsipkan.')">@csrf<button class="jk-pill" type="submit">Unmatch</button></form>
<form method="POST" action="/safety/block" onsubmit="return confirm('Blokir user ini? Kamu tidak akan saling melihat lagi.')">@csrf<input type="hidden" name="user_id" value="{{ $other?->id }}"><button class="jk-pill" type="submit">Block</button></form>
<form method="POST" action="/safety/report">@csrf<input type="hidden" name="user_id" value="{{ $other?->id }}"><button class="jk-pill" type="submit">Laporkan</button></form>
</div>
@error('body')<div class="jk-alert err">{{ $message }}</div>@enderror
@endif
</div>
