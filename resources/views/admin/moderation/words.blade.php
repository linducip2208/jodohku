@extends('layouts.admin')
@section('title', 'Profanity Dictionary')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Kamus Kata Kasar (ID + EN)</h3></div>
<div class="card-body">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('admin.moderation.words.store') }}" class="row g-2 mb-3">
@csrf
<div class="col-md-3"><input name="word" class="form-control" placeholder="kata / pola regex" required></div>
<div class="col-md-2"><input name="replacement" class="form-control" placeholder="ganti (opsional)"></div>
<div class="col-md-2"><select name="language" class="form-select"><option value="id">Indonesia</option><option value="en">English</option></select></div>
<div class="col-md-2"><select name="severity" class="form-select"><option value="medium">medium</option><option value="low">low</option><option value="high">high</option></select></div>
<div class="col-md-2"><label class="form-check"><input type="checkbox" name="is_regex" value="1" class="form-check-input"> regex</label></div>
<div class="col-md-1"><button class="btn btn-primary">Tambah</button></div>
</form>
@php $words = $words ?? \App\Models\ProfanityWord::with('category')->latest('id')->take(50)->get(); @endphp
<div class="table-responsive"><table class="table table-vcenter">
<thead><tr><th>Kata</th><th>Bahasa</th><th>Severity</th><th>Aktif</th><th>Aksi</th></tr></thead>
<tbody>
@forelse($words as $w)
<tr><td><code>{{ $w->word }}</code>@if($w->is_regex) <span class="badge">regex</span>@endif<div class="text-secondary">→ {{ $w->replacement ?? '***' }}</div></td>
<td>{{ $w->language }}</td><td>{{ $w->severity ?? '—' }}</td>
<td>{!! $w->is_active ? '<span class="badge bg-green"><i class="ti ti-check"></i></span>' : '<span class="badge">—</span>' !!}</td>
<td>
<form method="POST" action="{{ route('admin.moderation.words.destroy', $w->id) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Hapus</button></form>
</td></tr>
@empty
<tr><td colspan="5">@include('components.empty-admin', ['title' => 'Kamus kosong'])</td></tr>
@endforelse
</tbody></table></div>
@if(isset($words) && method_exists($words, 'links'))<div class="card-footer">{{ $words->links() }}</div>@endif
</div></div>
@endsection
