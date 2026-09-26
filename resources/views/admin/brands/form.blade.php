@extends('layouts.admin')
@section('title', ($brand->exists ? 'Edit' : 'Baru').' Brand — Admin')
@section('content')
<h1>{{ $brand->exists ? 'Edit' : 'Brand baru' }}</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" enctype="multipart/form-data">
@csrf @if($brand->exists) @method('PUT') @endif
<div class="row">
<div class="col-md-6">
<div class="mb-2"><label>Nama brand</label><input name="name" class="form-control" maxlength="80" required value="{{ old('name', $brand->name) }}"></div>
<div class="mb-2"><label>Slug (kosongkan = otomatis)</label><input name="slug" class="form-control" maxlength="60" value="{{ old('slug', $brand->slug) }}" @if($brand->exists) readonly @endif></div>
<div class="mb-2"><label>Tagline</label><input name="tagline" class="form-control" maxlength="200" value="{{ old('tagline', $brand->tagline) }}"></div>
<div class="mb-2"><label>Domain (opsional, cth brand-anda.com)</label><input name="domain" class="form-control" maxlength="190" value="{{ old('domain', $brand->domain) }}"></div>
@if($brand->exists && $brand->domain)
<div class="mb-2"><div class="card"><div class="card-header">Verifikasi domain</div><div class="card-body">
@if($brand->domain_verified_at)<div class="alert alert-success">Terverifikasi {{ $brand->domain_verified_at->diffForHumans() }} ✅</div>
@else<div class="alert alert-warning">Belum terverifikasi — tanpa ini orang bisa klaim domain Anda.</div>@endif
<p>Token: <code>{{ $brand->verification_token ?? '—' }}</code></p>
<p class="text-muted">Cara 1 (DNS): TXT record <code>{{ $brand->domain }}</code> berisi token di atas.<br>Cara 2 (HTTP): arahkan DNS ke server ini — file verifikasi otomatis tayang.</p>
<form method="POST" action="{{ route('admin.brands.verify', $brand) }}">@csrf<button class="btn btn-info" type="submit">Cek verifikasi</button></form>
</div></div></div>
@endif
<div class="mb-2"><label>Warna primer</label><input type="color" name="primary_color" id="f-primary" value="{{ old('primary_color', $brand->primary_color ?? '#f43f5e') }}"></div>
<div class="mb-2"><label>Warna sekunder</label><input type="color" name="secondary_color" id="f-secondary" value="{{ old('secondary_color', $brand->secondary_color ?? '#8b5cf6') }}"></div>
<div class="mb-2"><label>Logo (PNG/JPG/WebP/SVG ≤2MB)</label><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg" class="form-control">
@if($brand->logoUrl())<br><img src="{{ $brand->logoUrl() }}" alt="Logo" style="max-height:60px">@endif</div>
<div class="mb-2"><label>Favicon (≤1MB)</label><input type="file" name="favicon" accept=".png,.jpg,.jpeg,.webp,.svg,.ico" class="form-control">
@if($brand->faviconUrl())<br><img src="{{ $brand->faviconUrl() }}" alt="Favicon" style="max-height:32px">@endif</div>
</div>
<div class="col-md-6">
<div class="mb-2"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active))> Aktif</label></div>
<div class="mb-2"><label><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $brand->is_default))> Jadikan default</label></div>
<div class="mb-2"><label>Lisensi berakhir (kosong = selamanya)</label><input type="date" name="expires_at" class="form-control" value="{{ old('expires_at', $brand->expires_at?->format('Y-m-d')) }}"></div>
<div class="mb-2"><label>Maks user (kosong = tanpa batas)</label><input type="number" name="max_users" class="form-control" min="1" value="{{ old('max_users', $brand->max_users) }}"></div>
<div class="mb-2"><strong>Copy landing</strong> (kosong = default)</div>
<div class="mb-2"><label>Hero title</label><input name="content[hero_title]" class="form-control" maxlength="120" value="{{ old('content.hero_title', $brand->content['hero_title'] ?? '') }}"></div>
<div class="mb-2"><label>Hero subtitle</label><input name="content[hero_subtitle]" class="form-control" maxlength="300" value="{{ old('content.hero_subtitle', $brand->content['hero_subtitle'] ?? '') }}"></div>
<div class="mb-2"><label>Teks CTA</label><input name="content[cta_text]" class="form-control" maxlength="60" value="{{ old('content.cta_text', $brand->content['cta_text'] ?? '') }}"></div>
<div class="mb-2"><label>Email pengirim (opsional, harus domain terverifikasi SPF/DKIM)</label><input type="email" name="mail_from_address" class="form-control" maxlength="190" value="{{ old('mail_from_address', $brand->mail_from_address) }}"></div>
<div class="mb-2"><label>Nama pengirim</label><input name="mail_from_name" class="form-control" maxlength="120" value="{{ old('mail_from_name', $brand->mail_from_name) }}"></div>
<div class="mb-2"><strong>Fitur per-brand</strong> (kosong = ikut default global)<br>
@foreach(['taaruf' => 'Taaruf', 'counselor' => 'Konselor', 'community' => 'Komunitas', 'events' => 'Events', 'gifts' => 'Gifts', 'boost' => 'Boost'] as $k => $label)
<label style="margin-right:12px"><input type="checkbox" name="features[{{ $k }}]" value="1" @checked(old('features.'.$k, ($brand->features[$k] ?? true)))> {{ $label }}</label>
@endforeach
</div>
<div class="mb-2"><div class="card"><div class="card-header">Live preview</div><div class="card-body" id="brand-preview">
<div style="font-weight:800;font-size:22px"><span id="pv-name">{{ $brand->name ?: 'Nama Brand' }}</span></div>
<p class="text-muted" id="pv-tag">{{ $brand->tagline ?: 'Tagline brand' }}</p>
<a href="#" class="btn" id="pv-btn" onclick="return false">Contoh tombol</a>
</div></div></div>
</div>
</div>
<button class="btn btn-primary" type="submit">Simpan</button>
<a class="btn btn-secondary" href="{{ route('admin.brands') }}">Kembali</a>
</form>
<script>
(function () {
    var p = document.getElementById('f-primary'), s = document.getElementById('f-secondary');
    function paint() {
        var pv = document.getElementById('brand-preview');
        if (pv && p && s) pv.style.borderTop = '4px solid ' + p.value;
        var btn = document.getElementById('pv-btn');
        if (btn && p && s) btn.style.background = 'linear-gradient(135deg,' + p.value + ',' + s.value + ')';
        if (btn) { btn.style.color = '#fff'; btn.style.border = '0'; }
    }
    if (p) p.addEventListener('input', paint);
    if (s) s.addEventListener('input', paint);
    paint();
})();
</script>
@endsection
