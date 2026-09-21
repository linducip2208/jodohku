@extends('layouts.admin')
@section('title', 'Virtual')
@section('content')
@livewire('admin-virtual-stats')
<div class="card mt-3"><div class="card-header"><h3 class="card-title">Virtual Profiles (VirtualMemberService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Status</th></tr></thead><tbody>
@foreach(\App\Models\VirtualProfile::take(20)->get() as $v)
<tr><td>{{ $v->id }}</td><td>{{ $v->user_id ?? '—' }}</td><td>{{ $v->status ?? 'aktif' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
