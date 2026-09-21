@extends('layouts.admin')
@section('title', 'Feature Flags')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Feature Flags (config/jodohku.php)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Fitur</th><th>Status</th></tr></thead><tbody>
@foreach((array) config('jodohku.features', []) as $k => $v)
<tr><td>{{ $k }}</td><td>{{ $v ? '✅ Aktif' : '⛔ Mati' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
