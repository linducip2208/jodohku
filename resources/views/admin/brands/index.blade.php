@extends('layouts.admin')
@section('title', 'Whitelabel Brands — Admin')
@section('content')
<h1>Whitelabel Brands</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<p><a class="btn btn-primary" href="{{ route('admin.brands.create') }}">+ Brand baru</a>
<a class="btn btn-success" href="{{ route('admin.brands.wizard') }}">🧭 Wizard onboarding</a></p>
<table class="table"><thead><tr><th>Brand</th><th>Domain</th><th>Warna</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($brands as $b)<tr>
<td><strong>{{ $b->name }}</strong> <code>{{ $b->slug }}</code>@if($b->is_default) <span class="badge bg-primary">default</span>@endif<br><small class="text-muted">{{ $b->tagline }}</small></td>
<td>{{ $b->domain ?? '—' }}</td>
<td><span style="display:inline-block;width:18px;height:18px;border-radius:6px;background:{{ $b->primary_color }}"></span> <span style="display:inline-block;width:18px;height:18px;border-radius:6px;background:{{ $b->secondary_color }}"></span></td>
<td>{{ $b->is_active ? 'aktif' : 'nonaktif' }}</td>
<td style="white-space:nowrap">
<a class="btn btn-sm btn-secondary" href="{{ route('admin.brands.edit', $b) }}">Edit</a>
<a class="btn btn-sm btn-info" href="{{ route('admin.brands.export', $b) }}">Export</a>
<form method="POST" action="{{ route('admin.brands.destroy', $b) }}" style="display:inline" onsubmit="return confirm('Hapus brand?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit">Hapus</button></form>
</td></tr>
@empty<tr><td colspan="5">Belum ada brand. Buat satu atau impor paket.</td></tr>@endforelse
</tbody></table>
{{ $brands->links() }}
<h2>Impor paket brand (.zip)</h2>
<form method="POST" action="{{ route('admin.brands.import') }}" enctype="multipart/form-data">@csrf
<div class="mb-2"><input type="file" name="package" accept=".zip" required class="form-control"></div>
<label><input type="checkbox" name="activate" value="1"> Jadikan default & aktifkan</label><br><br>
<button class="btn btn-primary" type="submit">Impor</button></form>
@endsection
