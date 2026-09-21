@extends('layouts.admin')
@section('title', 'Notifications')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Notifications (NotificationService)</h3></div>
<div class="card-body">
<p class="text-secondary">Kirim broadcast ke segmen: semua, premium, verified, kota.</p>
<form method="POST" action="/admin/notifications/send">@csrf
<label class="form-label">Judul</label><input class="form-control" name="title" required>
<label class="form-label mt-2">Isi</label><textarea class="form-control" name="body" rows="3" required></textarea>
<button class="btn btn-primary mt-2" type="submit">Kirim Broadcast</button></form>
</div></div>
@endsection
