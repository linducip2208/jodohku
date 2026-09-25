@extends('layouts.member')
@section('title', 'Passport — Jodohku')
@section('content')
<h1 class="jk-h1">Passport</h1>
<p class="jk-muted">Jelajahi member di kota lain dengan lokasi virtual. Fitur Premium.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
@if($errors->has('passport'))<div class="jk-alert err">{{ $errors->first('passport') }}</div>@endif
<div class="jk-section"><div class="jk-h2">Lokasi aktif</div>
<p style="margin:0"><strong>{{ $effective['label'] }}</strong></p>
@if($effective['is_passport'])
<p class="jk-muted">Discovery, jarak, dan pencarian memakai lokasi virtual ini. Koordinat persis tidak pernah dibagikan.</p>
<form method="POST" action="/passport" style="margin-top:8px">@csrf @method('DELETE')<button class="jk-btn jk-btn-pass" type="submit">Kembali ke lokasiku</button></form>
@else
<p class="jk-muted">Saat ini memakai lokasi aslimu.</p>
@endif
</div>
@if(auth()->user()?->isPremium())
<div class="jk-section jk-form"><div class="jk-h2">Pilih kota tujuan</div>
<form method="POST" action="/passport">@csrf
<label>Kota</label>
<select name="city" id="passport-city" required>
@foreach($cities as $c)<option value="{{ $c['city'] }}" data-lat="{{ $c['lat'] }}" data-lng="{{ $c['lng'] }}" @selected(($effective['city'] ?? '') === $c['city'])>{{ $c['city'] }} — {{ $c['province'] }}</option>@endforeach
</select>
<input type="hidden" name="latitude" id="passport-lat" value="">
<input type="hidden" name="longitude" id="passport-lng" value="">
<button class="jk-submit" style="margin-top:12px" type="submit">Aktifkan Passport →</button></form>
</div>
<script>
(function () {
    var sel = document.getElementById('passport-city');
    function sync() {
        var o = sel.options[sel.selectedIndex];
        document.getElementById('passport-lat').value = o.getAttribute('data-lat');
        document.getElementById('passport-lng').value = o.getAttribute('data-lng');
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
@else
<div class="jk-section"><div class="jk-h2">Premium dulu</div>
<p class="jk-muted">Passport adalah fitur Premium.</p>
<a class="jk-btn jk-btn-like" style="text-decoration:none;text-align:center;display:block" href="/premium">Upgrade ke Premium</a></div>
@endif
@endsection
