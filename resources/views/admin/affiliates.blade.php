@extends('layouts.admin')
@section('title', 'Afiliasi — Admin')
@section('content')
<h1>Program Afiliasi</h1>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<h2>Pengajuan akun ({{ $accounts->total() }})</h2>
<table class="table"><thead><tr><th>User</th><th>Kode</th><th>Rate</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($accounts as $a)<tr>
<td>{{ $a->user?->displayName() ?? '#'.$a->user_id }}</td><td><code>{{ $a->code }}</code></td>
<td>{{ (float) $a->commission_rate * 100 }}%</td><td>{{ $a->status }}</td>
<td style="white-space:nowrap">
<form method="POST" action="{{ route('admin.affiliates.decide', $a) }}" style="display:inline">@csrf<input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-success" type="submit">Setujui</button></form>
<form method="POST" action="{{ route('admin.affiliates.decide', $a) }}" style="display:inline">@csrf<input type="hidden" name="action" value="suspend"><button class="btn btn-sm btn-warning" type="submit">Tangguhkan</button></form>
</td></tr>
@empty<tr><td colspan="5">Belum ada pengajuan.</td></tr>@endforelse
</tbody></table>
{{ $accounts->links() }}
<h2>Komisi tertunda ({{ $pending_commissions->total() }})</h2>
<table class="table"><thead><tr><th>Afiliasi</th><th>Payment</th><th>Nominal</th><th>Aksi</th></tr></thead><tbody>
@forelse($pending_commissions as $c)<tr>
<td>{{ $c->account?->user?->displayName() ?? '#' }} ({{ $c->account?->code }})</td>
<td>{{ $c->payment?->invoice_number ?? '#'.$c->payment_id }} · Rp{{ number_format($c->payment?->total_amount ?? 0, 0, ',', '.') }}</td>
<td>Rp{{ number_format($c->amount, 0, ',', '.') }}</td>
<td style="white-space:nowrap">
<form method="POST" action="{{ route('admin.affiliates.commission', $c) }}" style="display:inline">@csrf<input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-success" type="submit">Setujui</button></form>
<form method="POST" action="{{ route('admin.affiliates.commission', $c) }}" style="display:inline">@csrf<input type="hidden" name="action" value="pay"><button class="btn btn-sm btn-primary" type="submit">Bayar</button></form>
<form method="POST" action="{{ route('admin.affiliates.commission', $c) }}" style="display:inline">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-danger" type="submit">Tolak</button></form>
</td></tr>
@empty<tr><td colspan="4">Tidak ada komisi tertunda.</td></tr>@endforelse
</tbody></table>
{{ $pending_commissions->links() }}
@endsection
