@extends('layouts.admin')
@section('title', 'Questions')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Questions</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Kategori</th><th>Pertanyaan</th><th>Tipe</th></tr></thead><tbody>
@foreach(\App\Models\Question::take(25)->get() as $q)
<tr><td>{{ $q->id }}</td><td>{{ $q->category_id ?? $q->question_category_id ?? '—' }}</td><td>{{ \Illuminate\Support\Str::limit($q->text ?? $q->question ?? '', 70) }}</td><td>{{ $q->type ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
