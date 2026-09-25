<div x-data="{ open: false }" wire:poll.10s @call-invited.window="window.JodohkuCall?.start($event.detail.conversationId, $event.detail.callId, $event.detail.type, true)">
<div id="jk-chat-offline" role="alert">📡 Kamu offline — pesan akan coba dikirim ulang saat koneksi kembali.</div>
<script>
(function () {
    function jkChatNet() {
        var bar = document.getElementById('jk-chat-offline');
        if (bar) bar.classList.toggle('show', !navigator.onLine);
    }
    window.addEventListener('online', jkChatNet);
    window.addEventListener('offline', jkChatNet);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', jkChatNet);
    else jkChatNet();
})();
</script>
@if(!$conv)
@include('components.empty', ['icon' => 'chat', 'title' => 'Percakapan tidak ditemukan', 'hint' => 'Kembali ke inbox.'])
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
@if(!empty($courtshipProgress))
<div class="jk-section" style="border-left:4px solid #8b5cf6" aria-label="Progres taaruf">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<strong>Taaruf: {{ $courtshipProgress['label'] }}</strong>
<span class="jk-muted" style="font-size:12px">Tahap {{ $courtshipProgress['index'] }} dari {{ $courtshipProgress['total'] }}</span>
<a class="jk-pill" href="/biro-jodoh/taaruf/{{ $courtshipProgress['courtship_id'] }}">Lihat perjalanan</a>
</div>
<div class="jk-progress" style="margin-top:8px"><div style="width:{{ (int) ($courtshipProgress['index'] / max(1, $courtshipProgress['total']) * 100) }}%"></div></div>
</div>
@elseif(!empty($canStartTaaruf))
<div class="jk-section" aria-label="Mulai taaruf">
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<div style="flex:1;min-width:180px"><strong>Siap ke tahap serius?</strong><div class="jk-muted" style="font-size:12px">Kalian sudah match. Mulai perjalanan taaruf terpandu.</div></div>
<form method="POST" action="/biro-jodoh/taaruf/mulai" style="display:inline">@csrf<input type="hidden" name="partner_id" value="{{ $other?->id }}"><input type="hidden" name="conversation_id" value="{{ $conv?->id }}"><button class="jk-btn jk-btn-like" style="padding:8px 16px" type="submit">Mulai Taaruf</button></form>
</div>
</div>
@endif
@if(in_array($peerRisk ?? null, ['medium', 'high'], true))
<div class="jk-alert err" role="alert">Perhatikan keamanan saat berkomunikasi. Jangan kirim uang atau kode OTP kepada orang lain.</div>
@endif
@if(!empty($activeCall))
<div class="jk-section" role="alert" style="border-left:4px solid #ec4899"
  x-data="{ cstate: 'idle', elapsed: '00:00', muted: false, camOff: false }"
  x-init="
    window.addEventListener('call:state', e => { cstate = e.detail.state; });
    window.addEventListener('call:tick', e => { elapsed = e.detail.elapsed; });
    window.addEventListener('call:local-stream', e => { $refs.localVideo.srcObject = e.detail.stream; });
    window.addEventListener('call:remote-stream', e => { $refs.remoteVideo.srcObject = e.detail.stream; });
    @if($activeCall->status->value === 'ongoing') window.JodohkuCall?.start({{ $conv->id }}, {{ $activeCall->id }}, '{{ $activeCall->type }}', {{ (int) $activeCall->caller_id === (int) ($me?->id) ? 'true' : 'false' }}); @endif
  ">
<div class="jk-h2">{{ $activeCall->type === 'video' ? 'Panggilan video' : 'Panggilan suara' }} — {{ $activeCall->status->label() }} <span x-show="elapsed !== '00:00'" x-text="elapsed" style="font-size:13px"></span></div>
<div x-show="['calling','ringing','ongoing','reconnecting'].includes(cstate)" style="margin-top:8px">
<div style="position:relative;background:#09090b;border-radius:14px;overflow:hidden;min-height:180px">
<video x-ref="remoteVideo" autoplay playsinline style="width:100%;max-height:320px;background:#09090b"></video>
<video x-ref="localVideo" autoplay playsinline muted style="position:absolute;right:10px;bottom:10px;width:110px;border-radius:10px;background:#27272a"></video>
<div x-show="cstate === 'reconnecting'" style="position:absolute;top:10px;left:10px;background:rgba(0,0,0,.6);color:#fff;font-size:12px;border-radius:8px;padding:4px 10px">Menyambung ulang…</div>
</div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
<button class="jk-pill" @click="muted = window.JodohkuCall.toggleMute()" x-text="muted ? 'Unmute' : 'Mute'">Mute</button>
<button class="jk-pill" @click="camOff = !window.JodohkuCall.toggleCamera()" x-show="'{{ $activeCall->type }}' === 'video'">Kamera</button>
<button class="jk-pill" @click="window.JodohkuCall.switchCamera()" x-show="'{{ $activeCall->type }}' === 'video'">Ganti kamera</button>
</div>
</div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
@if($activeCall->status->value === 'ringing' && (int) $activeCall->receiver_id === (int) ($me?->id))
<button class="jk-btn jk-btn-like" style="padding:8px 16px" @click="window.JodohkuCall?.start({{ $conv->id }}, {{ $activeCall->id }}, '{{ $activeCall->type }}', false)" wire:click="answerCall({{ $activeCall->id }}, 'accept')">Angkat</button>
<button class="jk-pill" wire:click="answerCall({{ $activeCall->id }}, 'reject')">Tolak</button>
@elseif($activeCall->status->value === 'ringing')
<button class="jk-pill" wire:click="answerCall({{ $activeCall->id }}, 'cancel')">Batalkan</button>
@else
<button class="jk-pill" @click="window.JodohkuCall?.hangup()" wire:click="answerCall({{ $activeCall->id }}, 'end')">Akhiri</button>
@endif
</div>
</div>
@endif
@if(($scheduledItems ?? collect())->isNotEmpty())
<div class="jk-section"><div class="jk-h2">Terjadwal ({{ $scheduledItems->count() }})</div>
@foreach($scheduledItems as $s)
<div style="display:flex;gap:8px;align-items:center;font-size:12px;margin-top:4px">
<span class="jk-muted">{{ $s->send_at?->format('d M H:i') }}</span><span style="flex:1">{{ \Illuminate\Support\Str::limit($s->body, 60) }}</span>
<button class="jk-pill" wire:click="cancelScheduled({{ $s->id }})">Batal</button>
</div>
@endforeach
</div>
@endif
<div x-data="{ galleryOpen:false, gallery: [], galleryCursor: null, galleryMore: false }" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px">
<button class="jk-pill" @click="galleryOpen = !galleryOpen; if (galleryOpen && gallery.length === 0) { fetch('/chat/{{ $conv->id }}/gallery?per_page=12', { headers:{ 'Accept':'application/json' } }).then(r => r.json()).then(j => { gallery = j.data || []; galleryCursor = j.next_cursor; galleryMore = j.has_more; }).catch(() => {}) }" aria-expanded="false">Lihat media</button>
<a class="jk-pill" href="/chat/{{ $conv->id }}/export?format=csv">Ekspor chat</a>
<a class="jk-pill" href="/chat/saved">Pesan tersimpan</a>
@if(!empty($courtshipStage))<a class="jk-pill" href="/biro-jodoh/taaruf">Topik taaruf hari ini</a>@endif
</div>
<div x-show="galleryOpen" style="display:none" class="jk-section" aria-label="Galeri media">
<div class="jk-h2">Media percakapan</div>
<div class="jk-grid" style="grid-template-columns:repeat(3,1fr)">
<template x-for="g in gallery" :key="g.id"><div class="jk-card"><div class="jk-photo" style="aspect-ratio:1/1"><template x-if="String(g.mime_type || '').startsWith('image/')"><img :src="'/chat/attachments/' + g.id" alt="Lampiran" loading="lazy"></template><template x-if="!String(g.mime_type || '').startsWith('image/')"><div class="jk-photo-fallback" x-text="g.file_name || 'File'"></div></template></div></div></template>
</div>
<p class="jk-muted" x-show="gallery.length === 0">Belum ada media di percakapan ini.</p>
</div>
@if($messages->isEmpty())
<div class="jk-section" aria-label="Mulai percakapan">
<div class="jk-h2">Mulai percakapan yang bermakna</div>
<p class="jk-muted">Coba tanyakan:</p>
<ul>
<li>“Apa yang biasanya kamu nikmati di akhir pekan?”</li>
<li>“Apa yang kamu cari dalam pernikahan?”</li>
<li>“Hal apa yang paling penting bagimu dalam kehidupan keluarga?”</li>
</ul>
</div>
@endif
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px" id="msgList">
@foreach($messages as $m)
@php $mine = $me && $m->sender_id === $me->id; @endphp
<div style="display:flex;{{ $mine ? 'justify-content:flex-end' : 'justify-content:flex-start' }}">
<div class="jk-bubble {{ $mine ? 'me' : 'them' }}">
@if($m->replyTo)<div style="font-size:11px;opacity:.75;border-left:2px solid currentColor;padding-left:6px;margin-bottom:4px">{{ \Illuminate\Support\Str::limit($m->replyTo->body ?? '', 60) }}</div>@endif
@if($m->type === 'sticker')
<div style="font-size:44px;line-height:1">{{ $m->body }}</div>
@elseif($m->type === 'poll')
<div><strong>{{ $m->body }}</strong></div>
<div style="display:flex;flex-direction:column;gap:4px;margin-top:6px">
@foreach(($m->metadata['options'] ?? []) as $idx => $opt)
<button wire:click="votePoll({{ $m->id }}, {{ $idx }})" style="text-align:left;background:rgba(127,127,127,.12);border:0;border-radius:8px;padding:6px 10px;cursor:pointer;font-size:12px">{{ $opt }}@if(isset($pollResults[$m->id])) ({{ $pollResults[$m->id]['options'][$idx]['votes'] ?? 0 }})@endif</button>
@endforeach
</div>
@if(isset($pollResults[$m->id]))<div class="jk-muted" style="font-size:11px;margin-top:4px">Total {{ $pollResults[$m->id]['total_votes'] }} suara</div>@endif
@else
<div>{{ $m->body }}</div>
@endif
@isset($translations[$m->id])
<div style="font-size:11px;opacity:.8;border-top:1px dashed currentColor;margin-top:4px;padding-top:4px">Terjemahan: {{ $translations[$m->id] }}</div>
@endisset
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
<button wire:click="translate({{ $m->id }})" style="background:none;border:0;cursor:pointer;color:inherit" aria-label="Terjemahkan">Terjemahkan</button>
<button wire:click="delete({{ $m->id }})" style="background:none;border:0;cursor:pointer;color:inherit" aria-label="Hapus pesan">Hapus</button>
<button wire:click="toggleBookmark({{ $m->id }})" style="background:none;border:0;cursor:pointer;color:inherit" aria-label="Simpan pesan">{{ in_array($m->id, $bookmarks ?? []) ? 'Tersimpan ✓' : 'Simpan' }}</button>
</div>
@else
<div style="margin-top:4px;display:flex;gap:6px;font-size:11px">
<button wire:click="translate({{ $m->id }})" style="background:none;border:0;cursor:pointer;color:inherit" aria-label="Terjemahkan">Terjemahkan</button>
<button wire:click="toggleBookmark({{ $m->id }})" style="background:none;border:0;cursor:pointer;color:inherit" aria-label="Simpan pesan">{{ in_array($m->id, $bookmarks ?? []) ? 'Tersimpan ✓' : 'Simpan' }}</button>
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
<form wire:submit.prevent="send" x-on:submit="if (!navigator.onLine) { window.jkToast?.('📡 Offline — pesan tidak dikirim, coba lagi saat online.'); $event.preventDefault(); }" style="display:flex;gap:8px;position:sticky;bottom:70px;background:var(--jk-card);padding:8px;border:1px solid var(--jk-line);border-radius:14px">
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
<details style="margin-top:8px">
<summary class="jk-pill" style="cursor:pointer;display:inline-block" aria-label="Opsi lanjutan">Opsi lanjutan</summary>
<div style="display:flex;flex-direction:column;gap:10px;margin-top:8px">
<form wire:submit.prevent="schedule" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
<div style="flex:2;min-width:180px"><label class="jk-muted" style="font-size:11px" for="sched-body">Jadwalkan pesan</label><input id="sched-body" class="jk-input" style="width:100%" wire:model="scheduleBody" placeholder="Tulis pesan terjadwal..." maxlength="2000"></div>
<div><label class="jk-muted" style="font-size:11px" for="sched-at">Waktu kirim</label><input id="sched-at" class="jk-input" type="datetime-local" wire:model="scheduleAt"></div>
<button class="jk-pill" type="submit">Jadwalkan</button>
</form>
@error('scheduleBody')<div class="jk-alert err">{{ $message }}</div>@enderror
<form wire:submit.prevent="sendSticker" style="display:flex;gap:8px;align-items:flex-end">
<div style="flex:1"><label class="jk-muted" style="font-size:11px" for="sticker-pick">Stiker</label><select id="sticker-pick" class="jk-input" style="width:100%" wire:model="sticker">
<option value="">Pilih stiker…</option>
@foreach(($stickerCatalog ?? []) as $st)<option value="{{ $st['emoji'] }}">{{ $st['emoji'] }} {{ $st['name'] }}</option>@endforeach
</select></div>
<button class="jk-pill" type="submit">Kirim Stiker</button>
</form>
@error('sticker')<div class="jk-alert err">{{ $message }}</div>@enderror
<form wire:submit.prevent="sendPoll" style="display:flex;flex-direction:column;gap:6px">
<label class="jk-muted" style="font-size:11px" for="poll-q">Polling baru (satu opsi per baris, min 2)</label>
<input id="poll-q" class="jk-input" wire:model="pollQuestion" placeholder="Pertanyaan polling..." maxlength="300">
<textarea class="jk-input" wire:model="pollOptions" rows="2" placeholder="Opsi 1&#10;Opsi 2" aria-label="Opsi polling, satu per baris"></textarea>
<div><button class="jk-pill" type="submit">Kirim Polling</button></div>
</form>
@error('pollQuestion')<div class="jk-alert err">{{ $message }}</div>@enderror
<form wire:submit.prevent="setDisappearing" style="display:flex;gap:8px;align-items:flex-end">
<div style="flex:1"><label class="jk-muted" style="font-size:11px" for="disappear-sel">Pesan menghilang</label><select id="disappear-sel" class="jk-input" style="width:100%" wire:model="disappearing">
<option value="">Mati</option>
<option value="3600">1 jam</option>
<option value="86400">1 hari</option>
<option value="604800">7 hari</option>
</select></div>
<button class="jk-pill" type="submit">Simpan</button>
</form>
@error('disappearing')<div class="jk-alert err">{{ $message }}</div>@enderror
<div style="display:flex;gap:6px;flex-wrap:wrap">
<button class="jk-pill" wire:click="inviteCall('voice')" title="Panggilan suara berbayar token">Voice Call</button>
<button class="jk-pill" wire:click="inviteCall('video')" title="Panggilan video berbayar token">Video Call</button>
</div>
</div>
</details>
<div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
<form method="POST" action="/chat/{{ $conv->id }}/unmatch" onsubmit="return confirm('Yakin unmatch? Percakapan akan diarsipkan.')">@csrf<button class="jk-pill" type="submit">Unmatch</button></form>
<form method="POST" action="/safety/block" onsubmit="return confirm('Blokir user ini? Kamu tidak akan saling melihat lagi.')">@csrf<input type="hidden" name="user_id" value="{{ $other?->id }}"><button class="jk-pill" type="submit">Block</button></form>
<form method="POST" action="/safety/report">@csrf<input type="hidden" name="user_id" value="{{ $other?->id }}"><button class="jk-pill" type="submit">Laporkan</button></form>
</div>
@error('body')<div class="jk-alert err">{{ $message }}</div>@enderror
@endif
</div>
