@extends('layouts.admin')
@section('title', 'Versions')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Questionnaire Versions</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Versi</th><th>Aktif</th></tr></thead><tbody>
@foreach(\App\Models\QuestionnaireVersion::take(20)->get() as $v)
<tr><td>{{ $v->id }}</td><td>{{ $v->version ?? $v->name ?? $v->id }}</td><td>{{ $v->is_active ? '✅' : '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
