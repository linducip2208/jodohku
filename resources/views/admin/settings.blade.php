@extends('layouts.admin')
@section('title', 'Settings')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Settings</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>
@foreach(\App\Models\Setting::take(30)->get() as $s)
<tr><td>{{ $s->key }}</td><td>{{ \Illuminate\Support\Str::limit(is_string($s->value) ? $s->value : json_encode($s->value), 80) }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
