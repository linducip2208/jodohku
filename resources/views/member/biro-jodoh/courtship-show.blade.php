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

<div class="jk-section"><div class="jk-h2">Wali</div>
@if($data->guardian_name)
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
@endsection
