@extends('layouts.admin')
@section('title', 'Fraud')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Fraud Events (FraudDetectionService + ScamDetectionService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Skor</th><th>Alasan</th></tr></thead><tbody>
@foreach(\App\Models\FraudEvent::latest('id')->take(25)->get() as $f)
<tr><td>{{ $f->id }}</td><td>{{ $f->user_id }}</td><td>{{ $f->score ?? $f->risk_score ?? '—' }}</td><td>{{ \Illuminate\Support\Str::limit($f->reason ?? $f->details ?? '', 60) }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
