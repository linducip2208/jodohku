@extends('layouts.admin')
@section('title', 'Profiles')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Profiles</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>User</th><th>Headline</th><th>Pekerjaan</th><th>Pendidikan</th><th>Lengkap</th></tr></thead><tbody>
@foreach(\App\Models\Profile::with('user')->latest('id')->take(25)->get() as $p)
<tr><td>{{ $p->user?->displayName() ?? $p->user_id }}</td><td>{{ \Illuminate\Support\Str::limit($p->headline ?? '', 40) }}</td><td>{{ $p->occupation }}</td><td>{{ $p->education }}</td><td>{{ $p->is_complete ? '✅' : '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
