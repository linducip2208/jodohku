@extends('layouts.admin')
@section('title', 'Statistik Brand — Admin')
@section('content')
<h1>Statistik: {{ $brand->name }}</h1>
<p><a class="btn btn-secondary" href="{{ route('admin.brands.edit', $brand) }}">Kelola brand</a></p>
@if($brand->expires_at)<div class="alert {{ $brand->licensed() ? 'alert-info' : 'alert-danger' }}">Lisensi: {{ $brand->expires_at->format('d M Y') }} ({{ $brand->licensed() ? 'aktif' : 'KEDALUWARSA — fallback ke Jodohku' }})</div>@endif
@if($brand->max_users)<div class="alert alert-info">Kuota user: {{ number_format($stats['users_total']) }} / {{ number_format($brand->max_users) }}</div>@endif
<table class="table"><tbody>
<tr><th>Total user</th><td>{{ number_format($stats['users_total']) }}</td></tr>
<tr><th>Terverifikasi</th><td>{{ number_format($stats['users_verified']) }}</td></tr>
<tr><th>Premium</th><td>{{ number_format($stats['users_premium']) }}</td></tr>
<tr><th>Aktif 7 hari</th><td>{{ number_format($stats['users_active_7d']) }}</td></tr>
<tr><th>Matches</th><td>{{ number_format($stats['matches']) }}</td></tr>
<tr><th>Pesan terkirim</th><td>{{ number_format($stats['messages_sent']) }}</td></tr>
<tr><th>Pendapatan (paid)</th><td>Rp{{ number_format((float) $stats['revenue_paid'], 0, ',', '.') }}</td></tr>
</tbody></table>
<p class="text-muted">Dasar penagihan ke klien: user aktif & pendapatan brand.</p>
@endsection
