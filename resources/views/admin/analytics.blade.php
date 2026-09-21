@extends('layouts.admin')
@section('title', 'Analytics')
@section('content')
<div class="row row-cards">
@include('components.stat-card', ['label' => 'DAU', 'value' => \App\Models\User::where('last_active_at', '>', now()->subDay())->count(), 'ti' => 'ti-dashboard'])
@include('components.stat-card', ['label' => 'WAU', 'value' => \App\Models\User::where('last_active_at', '>', now()->subDays(7))->count(), 'ti' => 'ti-chart-line'])
@include('components.stat-card', ['label' => 'MAU', 'value' => \App\Models\User::where('last_active_at', '>', now()->subDays(30))->count(), 'ti' => 'ti-chart-area'])
@include('components.stat-card', ['label' => 'Match rate', 'value' => (\App\Models\Like::count() ? round(\App\Models\UserMatch::count() / max(1, \App\Models\Like::count()) * 100, 1) . '%' : '—'), 'ti' => 'ti-heart'])
@include('components.stat-card', ['label' => 'Premium conv.', 'value' => (\App\Models\User::count() ? round(\App\Models\User::where('is_premium', true)->count() / max(1, \App\Models\User::count()) * 100, 1) . '%' : '—'), 'ti' => 'ti-star'])
@include('components.stat-card', ['label' => 'AI cost', 'value' => \App\Models\AiUsageLog::sum('cost') ?? 0, 'ti' => 'ti-brain'])
</div>
@php
$week = collect(range(6, 0))->map(fn ($i) => now()->subDays($i));
$weekLabels = $week->map(fn ($d) => $d->format('D'))->values();
try {
    $dau = $week->map(fn ($d) => \App\Models\User::whereDate('last_active_at', $d->toDateString())->count())->values();
    $wkMatches = $week->map(fn ($d) => \App\Models\UserMatch::whereDate('matched_at', $d->toDateString())->count())->values();
} catch (\Throwable) { $dau = $wkMatches = array_fill(0, 7, 0); }
@endphp
<div class="card mt-3"><div class="card-header"><h3 class="card-title">Tren aktivitas (7 hari aktual)</h3></div><div class="card-body"><canvas id="chAna" height="140"></canvas></div></div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
try { new Chart(document.getElementById('chAna'), {type:'line', data:{labels:{!! json_encode($weekLabels) !!}, datasets:[{label:'Aktif harian', data:{!! json_encode($dau) !!}, borderColor:'#8b5cf6', tension:.4},{label:'Matches', data:{!! json_encode($wkMatches) !!}, borderColor:'#f43f5e', tension:.4}]}}); } catch(e){}
</script>
@endpush
@endsection
