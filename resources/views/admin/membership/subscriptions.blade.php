@extends('layouts.admin')
@section('title', 'Subscriptions')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Subscriptions (SubscriptionService)</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>User</th><th>Plan</th><th>Status</th><th>Berakhir</th></tr></thead><tbody>
@foreach(\App\Models\Subscription::latest('id')->take(25)->get() as $s)
<tr><td>{{ $s->id }}</td><td>{{ $s->user_id }}</td><td>{{ $s->plan_id ?? $s->membership_plan_id ?? '—' }}</td><td>{{ $s->status }}</td><td>{{ $s->ends_at?->format('d M Y') ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></div>
@endsection
