@extends('layouts.admin')
@section('title', 'Email')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Email Templates</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Template</th><th>Preview</th></tr></thead><tbody>
@foreach(['welcome' => 'Selamat datang', 'verify-email' => 'Verifikasi email', 'password-reset' => 'Reset password', 'new-match' => 'Match baru', 'new-message' => 'Pesan baru', 'chat-request' => 'Chat request', 'subscription-active' => 'Premium aktif', 'payment-success' => 'Bayar sukses', 'payment-failed' => 'Bayar gagal', 'verification-approved' => 'Verifikasi OK', 'account-warning' => 'Peringatan'] as $tpl => $label)
<tr><td>{{ $label }}<div class="text-secondary">emails.{{ $tpl }}</div></td><td><span class="badge bg-green-lt">Aktif</span></td></tr>
@endforeach
</tbody></table></div></div>
@endsection
