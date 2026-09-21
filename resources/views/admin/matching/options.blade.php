@extends('layouts.admin')
@section('title', 'Options')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Question Options</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Question</th><th>Opsi</th><th>Skor</th></tr></thead><tbody>
@foreach(\App\Models\QuestionOption::take(25)->get() as $o)
<tr><td>{{ $o->id }}</td><td>{{ $o->question_id }}</td><td>{{ \Illuminate\Support\Str::limit($o->text ?? $o->label ?? '', 60) }}</td><td>{{ $o->score ?? $o->weight ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
