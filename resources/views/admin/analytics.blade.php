@extends('layouts.admin')
@section('title', 'Analytics')
@section('content')
<div class="row row-cards">
@include('components.stat-card', ['label' => 'DAU', 'value' => \App\Models\User::where('last_active_at', '>', now()->subDay())->count(), 'icon' => '📊'])
@include('components.stat-card', ['label' => 'WAU', 'value' => \App\Models\User::where('last_active_at', '>', now()->subDays(7))->count(), 'icon' => '📈'])
@include('components.stat-card', ['label' => 'MAU', 'value' => \App\Models\User::where('last_active_at', '>', now()->subDays(30))->count(), 'icon' => '📉'])
@include('components.stat-card', ['label' => 'Match rate', 'value' => (\App\Models\Like::count() ? round(\App\Models\UserMatch::count() / max(1, \App\Models\Like::count()) * 100, 1) . '%' : '—'), 'icon' => '💘'])
@include('components.stat-card', ['label' => 'Premium conv.', 'value' => (\App\Models\User::count() ? round(\App\Models\User::where('is_premium', true)->count() / max(1, \App\Models\User::count()) * 100, 1) . '%' : '—'), 'icon' => '⭐'])
@include('components.stat-card', ['label' => 'AI cost', 'value' => \App\Models\AiUsageLog::sum('cost') ?? 0, 'icon' => '🧠'])
</div>
<div class="card mt-3"><div class="card-header"><h3 class="card-title">Tren aktivitas</h3></div><div class="card-body"><canvas id="chAna" height="140"></canvas></div></div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
try { new Chart(document.getElementById('chAna'), {type:'line', data:{labels:['Sen','Sel','Rab','Kam','Jum','Sab','Min'], datasets:[{label:'DAU', data:[120,180,150,220,260,300,240], borderColor:'#8b5cf6', tension:.4},{label:'Matches', data:[20,35,28,44,52,60,48], borderColor:'#f43f5e', tension:.4}]}}); } catch(e){}
</script>
@endpush
@endsection
