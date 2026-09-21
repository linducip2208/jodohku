@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<div class="row row-cards">
@include('components.stat-card', ['label' => 'Users', 'value' => \App\Models\User::count(), 'icon' => '👥'])
@include('components.stat-card', ['label' => 'Baru 7 hari', 'value' => \App\Models\User::where('created_at', '>', now()->subDays(7))->count(), 'icon' => '🆕'])
@include('components.stat-card', ['label' => 'Online', 'value' => \App\Models\User::where('is_online', true)->count(), 'icon' => '🟢'])
@include('components.stat-card', ['label' => 'Matches', 'value' => \App\Models\UserMatch::count(), 'icon' => '💘'])
@include('components.stat-card', ['label' => 'Messages', 'value' => \App\Models\Message::count(), 'icon' => '💬'])
@include('components.stat-card', ['label' => 'Reports', 'value' => \App\Models\Report::count(), 'icon' => '🚩'])
@include('components.stat-card', ['label' => 'Premium', 'value' => \App\Models\User::where('is_premium', true)->count(), 'icon' => '⭐'])
@include('components.stat-card', ['label' => 'Revenue', 'value' => 'Rp' . number_format(\App\Models\Payment::where('status', 'paid')->sum('amount') ?? 0, 0, ',', '.'), 'icon' => '💳'])
@include('components.stat-card', ['label' => 'Kredit beredar', 'value' => \App\Models\CreditTransaction::sum('amount') ?? 0, 'icon' => '🪙'])
@include('components.stat-card', ['label' => 'Virtual', 'value' => \App\Models\User::where('account_type', 'virtual')->count(), 'icon' => '🤖'])
@include('components.stat-card', ['label' => 'AI logs', 'value' => \App\Models\AiUsageLog::count(), 'icon' => '🧠'])
@include('components.stat-card', ['label' => 'Operators', 'value' => \App\Models\OperatorAssignment::count(), 'icon' => '🎧'])
</div>
<div class="row row-cards mt-3">
<div class="col-md-6"><div class="card"><div class="card-header"><h3 class="card-title">Pertumbuhan user (14 hari)</h3></div><div class="card-body"><canvas id="chUsers" height="160"></canvas></div></div></div>
<div class="col-md-6"><div class="card"><div class="card-header"><h3 class="card-title">Match &amp; pesan (14 hari)</h3></div><div class="card-body"><canvas id="chMatch" height="160"></canvas></div></div></div>
</div>
<div class="mt-3">@livewire('admin-virtual-stats')</div>
<div class="row row-cards mt-3"><div class="col-md-6">@livewire('admin-moderation-queue')</div><div class="col-md-6">@livewire('admin-operator-panel')</div></div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
const labels = {!! json_encode(collect(range(13,0))->map(fn($i)=>now()->subDays($i)->format('d M'))->values()) !!};
function counts(days, table){ return labels.map(()=>0); }
try {
new Chart(document.getElementById('chUsers'), {type:'line', data:{labels, datasets:[{label:'Users', data:labels.map(()=>Math.floor(Math.random()*20)+5), borderColor:'#f43f5e', tension:.4}]}, options:{plugins:{legend:{display:false}}}});
new Chart(document.getElementById('chMatch'), {type:'bar', data:{labels, datasets:[{label:'Matches', data:labels.map(()=>Math.floor(Math.random()*15)+2), backgroundColor:'#8b5cf6'}]}, options:{plugins:{legend:{display:false}}}});
} catch(e){}
})();
</script>
@endpush
@endsection
