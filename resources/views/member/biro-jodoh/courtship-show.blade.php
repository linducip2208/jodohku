@extends('layouts.member')
@section('title', 'Detail Taaruf — Jodohku')
@section('content')
<h1 class="jk-h1">Taaruf: {{ $data->partner?->displayName() }}</h1>
<p class="jk-muted">Tahap saat ini: <strong>{{ $data->stage->label() }}</strong> · Status: {{ $data->status->label() }}</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->any())<div class="jk-alert err">{{ $errors->first() }}</div>@endif

@if(!empty($journey))
<div class="jk-section"><div class="jk-h2">Perjalanan ({{ $journey['completed'] }}/{{ $journey['total'] }})</div>
<table class="jk-table"><tbody>
@foreach($journey['steps'] as $s)
<tr><td>{{ $s['done'] ? '✓' : '○' }} {{ $s['label'] }}</td><td class="jk-muted">{{ $s['detail'] }}</td></tr>
@endforeach
</tbody></table>
@if(!empty($journey['counseling_suggested']))<p class="jk-muted">Kamu sudah di tahap taaruf — pertimbangkan <a href="/biro-jodoh/konselor">konsultasi dengan konselor</a>.</p>@endif
</div>
@endif

@php
$taarufTopics = [];
try {
    $viewer = auth()->user();
    $partner = (int) $data->initiator_id === (int) $viewer->id ? $data->partner : $data->initiator;
    if ($partner) { $taarufTopics = app(\App\Services\PersonalizationService::class)->getRecommendedTopics($viewer, $partner, 3); }
} catch (\Throwable) {}
$passedStages = collect($data->stage_history ?? [])->pluck('to')->filter()->unique()->values()->all();
@endphp
<div class="jk-section" aria-labelledby="h-sum"><div class="jk-h2" id="h-sum">Ringkasan perjalanan</div>
<div class="jk-muted" style="font-size:13px">Tahapan dilalui: {{ empty($passedStages) ? 'kenalan (tahap awal)' : 'kenalan → ' . implode(' → ', $passedStages) }} · Wali: {{ $data->guardian_approved_at ? 'restu tercatat' : 'belum ada restu' }}</div>
@if(!empty($taarufTopics))
<div style="margin-top:8px"><strong>Saran topik berikutnya:</strong>
<ul style="margin:6px 0;padding-left:18px">
@foreach($taarufTopics as $t)<li>{{ $t['question'] ?? $t['topic'] }} <span class="jk-muted">({{ $t['why'] ?? '' }})</span></li>@endforeach
</ul></div>
@endif
</div>

<div class="jk-section"><div class="jk-h2">Wali</div>@if($data->guardian_name)
<p class="jk-muted">{{ $data->guardian_name }} ({{ $data->guardian_relation ?? 'wali' }})@if($data->guardian_approved_at) — restu tercatat. @else — belum ada restu. @endif</p>
@else
<p class="jk-muted">Belum ada data wali. Wali diperlukan sebelum khitbah.</p>
@endif
<form method="POST" action="/biro-jodoh/taaruf/{{ $data->id }}/wali">
@csrf @method('PUT')
<label for="guardian_name">Nama wali</label><input id="guardian_name" name="guardian_name" value="{{ old('guardian_name', $data->guardian_name) }}">
<label for="guardian_phone">Telepon wali</label><input id="guardian_phone" name="guardian_phone" value="{{ old('guardian_phone', $data->guardian_phone) }}">
<label for="guardian_relation">Hubungan</label><input id="guardian_relation" name="guardian_relation" value="{{ old('guardian_relation', $data->guardian_relation) }}" placeholder="cth: Ayah">
<button class="jk-submit" style="margin-top:10px" type="submit">Simpan Wali</button>
</form>
<form method="POST" action="/biro-jodoh/taaruf/{{ $data->id }}/wali/setuju" style="margin-top:8px">@csrf<button class="jk-pill" type="submit">Catat Restu Wali</button></form>
</div>

<div class="jk-section"><div class="jk-h2">Aksi Tahap</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="POST" action="/biro-jodoh/taaruf/{{ $data->id }}/lanjut">@csrf<button class="jk-btn jk-btn-like" type="submit">Lanjut ke Tahap Berikutnya</button></form>
<form method="POST" action="/biro-jodoh/taaruf/{{ $data->id }}/mundur" onsubmit="return confirm('Yakin mengakhiri taaruf dengan baik?')">@csrf<button class="jk-pill" type="submit">Mundur</button></form>
@if($data->conversation_id)<a class="jk-pill" href="/chat/{{ $data->conversation_id }}">Buka Chat</a>@endif
</div></div>

@if($data->conversation_id)
<div class="jk-section" x-data="{ digest:null, loading:false }" aria-labelledby="h-digest">
<div class="jk-h2" id="h-digest">Ringkasan percakapan</div>
<p class="jk-muted">Ringkasan singkat obrolan taaruf — dibuat saat diminta, hanya untuk kalian berdua.</p>
<button class="jk-pill" @click="loading = true; fetch('/biro-jodoh/taaruf/{{ $data->id }}/ringkasan', { headers:{ 'Accept':'application/json' } }).then(r => r.json()).then(j => { digest = j.digest; loading = false; }).catch(() => { loading = false; })" x-show="!digest && !loading">Buat ringkasan</button>
<p class="jk-muted" x-show="loading">Menyusun ringkasan…</p>
<p x-show="digest" x-text="digest"></p>
</div>
@endif

@if($data->status->value === 'completed')
<div class="jk-section"><div class="jk-h2">Alhamdulillah!</div>
<p class="jk-muted">Perjalanan taaruf selesai. Bantu pasangan lain dengan membagikan kisahmu (data privat tidak ikut).</p>
<a class="jk-btn jk-btn-like" style="text-decoration:none;text-align:center" href="/biro-jodoh/kisah">Bagikan kisah sukses</a>
</div>
@endif
@endsection
