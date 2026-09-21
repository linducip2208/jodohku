@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<div class="row row-cards">
@include('components.stat-card', ['label' => 'Users', 'value' => \App\Models\User::count(), 'ti' => 'ti-users'])
@include('components.stat-card', ['label' => 'Baru 7 hari', 'value' => \App\Models\User::where('created_at', '>', now()->subDays(7))->count(), 'ti' => 'ti-user-plus'])
@include('components.stat-card', ['label' => 'Online', 'value' => \App\Models\User::where('is_online', true)->count(), 'ti' => 'ti-wifi'])
@include('components.stat-card', ['label' => 'Matches', 'value' => \App\Models\UserMatch::count(), 'ti' => 'ti-heart'])
@include('components.stat-card', ['label' => 'Messages', 'value' => \App\Models\Message::count(), 'ti' => 'ti-messages'])
@include('components.stat-card', ['label' => 'Reports', 'value' => \App\Models\Report::count(), 'ti' => 'ti-flag'])
@include('components.stat-card', ['label' => 'Premium', 'value' => \App\Models\User::where('is_premium', true)->count(), 'ti' => 'ti-star'])
@include('components.stat-card', ['label' => 'Revenue', 'value' => 'Rp' . number_format(\App\Models\Payment::where('status', 'paid')->sum('amount') ?? 0, 0, ',', '.'), 'ti' => 'ti-credit-card'])
@include('components.stat-card', ['label' => 'Kredit beredar', 'value' => \App\Models\CreditTransaction::sum('amount') ?? 0, 'ti' => 'ti-coins'])
@include('components.stat-card', ['label' => 'Virtual', 'value' => \App\Models\User::where('account_type', 'virtual')->count(), 'ti' => 'ti-robot'])
@include('components.stat-card', ['label' => 'AI logs', 'value' => \App\Models\AiUsageLog::count(), 'ti' => 'ti-brain'])
@include('components.stat-card', ['label' => 'Operators', 'value' => \App\Models\OperatorAssignment::count(), 'ti' => 'ti-headset'])
</div>
@php
$days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i));
$dayLabels = $days->map(fn ($d) => $d->format('d M'))->values();
try {
    $regPerDay = $days->map(fn ($d) => \App\Models\User::whereDate('created_at', $d->toDateString())->count())->values();
    $matchPerDay = $days->map(fn ($d) => \App\Models\UserMatch::whereDate('matched_at', $d->toDateString())->count())->values();
    $msgPerDay = $days->map(fn ($d) => \App\Models\Message::whereDate('created_at', $d->toDateString())->count())->values();
} catch (\Throwable) { $regPerDay = $matchPerDay = $msgPerDay = array_fill(0, 14, 0); }
@endphp
<div class="row row-cards mt-3">
<div class="col-md-6"><div class="card"><div class="card-header"><h3 class="card-title">Registrasi user (14 hari)</h3></div><div class="card-body"><canvas id="chUsers" height="160"></canvas></div></div></div>
<div class="col-md-6"><div class="card"><div class="card-header"><h3 class="card-title">Match &amp; pesan (14 hari)</h3></div><div class="card-body"><canvas id="chMatch" height="160"></canvas></div></div></div>
</div>
<div class="mt-3">@livewire('admin-virtual-stats')</div>
<div class="row row-cards mt-3"><div class="col-md-6">@livewire('admin-moderation-queue')</div><div class="col-md-6">@livewire('admin-operator-panel')</div></div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
const labels = {!! json_encode($dayLabels) !!};
const regs = {!! json_encode($regPerDay) !!};
const matches = {!! json_encode($matchPerDay) !!};
const msgs = {!! json_encode($msgPerDay) !!};
try {
new Chart(document.getElementById('chUsers'), {type:'line', data:{labels, datasets:[{label:'Registrasi', data:regs, borderColor:'#f43f5e', tension:.4, fill:false}]}, options:{plugins:{legend:{display:false}}}});
new Chart(document.getElementById('chMatch'), {type:'bar', data:{labels, datasets:[{label:'Matches', data:matches, backgroundColor:'#8b5cf6'},{label:'Pesan', data:msgs, backgroundColor:'#38bdf8'}]}, options:{plugins:{legend:{display:true}}}});
} catch(e){}
})();
</script>
@endpush
@endsection
