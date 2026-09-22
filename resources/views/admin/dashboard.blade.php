@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<div class="row row-cards">
@foreach ([
    ['label' => 'Users', 'value' => $metrics['users'], 'ti' => 'ti-users'],
    ['label' => 'Baru 7 hari', 'value' => $metrics['users_7d'], 'ti' => 'ti-user-plus'],
    ['label' => 'Online', 'value' => $metrics['online'], 'ti' => 'ti-wifi'],
    ['label' => 'Matches', 'value' => $metrics['matches'], 'ti' => 'ti-heart'],
    ['label' => 'Messages', 'value' => $metrics['messages'], 'ti' => 'ti-messages'],
    ['label' => 'Reports', 'value' => $metrics['reports'], 'ti' => 'ti-flag'],
    ['label' => 'Premium', 'value' => $metrics['premium'], 'ti' => 'ti-star'],
    ['label' => 'Revenue', 'value' => $metrics['revenue_formatted'], 'ti' => 'ti-credit-card'],
    ['label' => 'Kredit beredar', 'value' => $metrics['credits'], 'ti' => 'ti-coins'],
    ['label' => 'Virtual', 'value' => $metrics['virtual'], 'ti' => 'ti-robot'],
    ['label' => 'AI logs', 'value' => $metrics['ai_logs'], 'ti' => 'ti-brain'],
    ['label' => 'Operators', 'value' => $metrics['operators'], 'ti' => 'ti-headset'],
] as $stat)
@include('components.stat-card', $stat)
@endforeach
</div>

<div class="row row-cards mt-3">
<div class="col-md-6">
<div class="card"><div class="card-header"><h3 class="card-title">Registrasi user (14 hari)</h3></div><div class="card-body"><canvas id="chUsers" height="160"></canvas></div></div>
</div>
<div class="col-md-6">
<div class="card"><div class="card-header"><h3 class="card-title">Match &amp; pesan (14 hari)</h3></div><div class="card-body"><canvas id="chMatch" height="160"></canvas></div></div>
</div>
</div>

<div class="mt-3">@livewire('admin-virtual-stats')</div>
<div class="row row-cards mt-3"><div class="col-md-6">@livewire('admin-moderation-queue')</div><div class="col-md-6">@livewire('admin-operator-panel')</div></div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels = @json($charts['labels']);
    const regs = @json($charts['registrations']);
    const matches = @json($charts['matches']);
    const msgs = @json($charts['messages']);
    try {
        new Chart(document.getElementById('chUsers'), {type: 'line', data: {labels, datasets: [{label: 'Registrasi', data: regs, borderColor: '#f43f5e', tension: .4, fill: false}]}, options: {plugins: {legend: {display: false}}}});
        new Chart(document.getElementById('chMatch'), {type: 'bar', data: {labels, datasets: [{label: 'Matches', data: matches, backgroundColor: '#8b5cf6'}, {label: 'Pesan', data: msgs, backgroundColor: '#38bdf8'}]}, options: {plugins: {legend: {display: true}}}});
    } catch (e) {}
})();
</script>
@endpush
@endsection
