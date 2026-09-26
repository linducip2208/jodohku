@extends('layouts.admin')
@section('title', 'Onboarding Brand — Admin')
@section('content')
<h1>Onboarding brand baru (4 langkah)</h1>
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card mb-3"><div class="card-header"><strong>Template</strong> — mulai dari preset sekali-klik (opsional)</div><div class="card-body" id="w-templates" style="display:flex;gap:8px;flex-wrap:wrap">
@php $templates = \App\Services\BrandService::TEMPLATES; @endphp
@foreach($templates as $key => $t)
<button type="button" class="btn btn-secondary" data-tpl="{{ $key }}" data-name="{{ $t['name'] }}" data-tag="{{ $t['tagline'] }}" data-p="{{ $t['primary'] }}" data-s="{{ $t['secondary'] }}" title="{{ $t['tagline'] }}">
<span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:linear-gradient(135deg,{{ $t['primary'] }},{{ $t['secondary'] }})"></span> {{ $t['name'] }}
</button>
@endforeach
</div></div>
<form id="wiz-form" method="POST" action="{{ route('admin.brands.store') }}" enctype="multipart/form-data">
@csrf
<div class="card mb-3"><div class="card-header"><strong>Langkah 1</strong> — Identitas</div><div class="card-body">
<div class="mb-2"><label>Nama brand</label><input name="name" id="w-name" class="form-control" maxlength="80" required value="{{ old('name') }}"></div>
<div class="mb-2"><label>Tagline</label><input name="tagline" id="w-tag" class="form-control" maxlength="200" value="{{ old('tagline') }}"></div>
<div class="mb-2"><label>Domain (opsional)</label><input name="domain" class="form-control" maxlength="190" placeholder="brand-anda.com" value="{{ old('domain') }}"></div>
</div></div>
<div class="card mb-3"><div class="card-header"><strong>Langkah 2</strong> — Warna (live preview di kanan)</div><div class="card-body">
<div class="mb-2"><label>Warna primer</label><input type="color" name="primary_color" id="w-primary" value="{{ old('primary_color', '#f43f5e') }}"></div>
<div class="mb-2"><label>Warna sekunder</label><input type="color" name="secondary_color" id="w-secondary" value="{{ old('secondary_color', '#8b5cf6') }}"></div>
</div></div>
<div class="card mb-3"><div class="card-header"><strong>Langkah 3</strong> — Logo & favicon</div><div class="card-body">
<div class="mb-2"><label>Logo (≤2MB)</label><input type="file" name="logo" id="w-logo" accept=".png,.jpg,.jpeg,.webp,.svg" class="form-control"></div>
<div class="mb-2"><label>Favicon (≤1MB)</label><input type="file" name="favicon" accept=".png,.jpg,.jpeg,.webp,.svg,.ico" class="form-control"></div>
</div></div>
<div class="card mb-3"><div class="card-header"><strong>Langkah 4</strong> — Fitur & selesai</div><div class="card-body">
@foreach(['taaruf' => 'Taaruf', 'counselor' => 'Konselor', 'community' => 'Komunitas', 'events' => 'Events', 'gifts' => 'Gifts', 'boost' => 'Boost'] as $k => $label)
<label style="margin-right:12px"><input type="checkbox" name="features[{{ $k }}]" value="1" checked> {{ $label }}</label>
@endforeach
<div class="mt-2"><label><input type="checkbox" name="is_active" value="1" checked> Aktifkan langsung</label></div>
</div></div>
<button class="btn btn-primary" type="submit">Buat brand</button>
<button class="btn btn-info" type="button" id="w-preview-btn">Simpan draf & preview landing</button>
</form>
<div class="card mt-3"><div class="card-header">Live preview</div><div class="card-body" id="w-preview">
<div style="display:flex;gap:12px;align-items:center">
<img id="w-logo-pv" alt="" style="max-height:48px;display:none">
<div><div style="font-weight:800;font-size:22px" id="w-name-pv">Nama Brand</div>
<div class="text-muted" id="w-tag-pv">Tagline</div></div></div>
<div class="mt-2"><a href="#" class="btn" id="w-btn-pv" onclick="return false">Contoh tombol</a></div>
<div class="mt-3"><iframe id="w-landing-pv" title="Preview landing" style="width:100%;height:480px;border:1px solid #ddd;border-radius:12px;display:none"></iframe></div>
</div></div>
<script>
(function () {
    var n = document.getElementById('w-name'), t = document.getElementById('w-tag'),
        p = document.getElementById('w-primary'), s = document.getElementById('w-secondary'),
        logo = document.getElementById('w-logo');
    function paint() {
        document.getElementById('w-name-pv').textContent = n.value || 'Nama Brand';
        document.getElementById('w-tag-pv').textContent = t.value || 'Tagline';
        var btn = document.getElementById('w-btn-pv');
        btn.style.background = 'linear-gradient(135deg,' + p.value + ',' + s.value + ')';
        btn.style.color = '#fff'; btn.style.border = '0';
        document.getElementById('w-preview').style.borderTop = '4px solid ' + p.value;
    }
    [n, t, p, s].forEach(function (el) { el.addEventListener('input', paint); });
    document.querySelectorAll('#w-templates [data-tpl]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            n.value = btn.getAttribute('data-name');
            t.value = btn.getAttribute('data-tag');
            p.value = btn.getAttribute('data-p');
            s.value = btn.getAttribute('data-s');
            paint();
        });
    });
    logo.addEventListener('change', function () {
        var f = logo.files[0], img = document.getElementById('w-logo-pv');
        if (f) { img.src = URL.createObjectURL(f); img.style.display = 'block'; }
        else { img.style.display = 'none'; }
    });
    paint();
    document.getElementById('w-preview-btn').addEventListener('click', function () {
        var token = document.querySelector('#wiz-form input[name=_token]').value;
        fetch('{{ route('admin.brands.wizard.draft') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ name: n.value, tagline: t.value, primary_color: p.value, secondary_color: s.value })
        }).then(function (r) { return r.json(); }).then(function (j) {
            var f = document.getElementById('w-landing-pv');
            f.src = j.preview_url; f.style.display = 'block';
            f.scrollIntoView({ behavior: 'smooth' });
        });
    });
})();
</script>
@endsection
