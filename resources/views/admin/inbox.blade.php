@extends('layouts.admin')
@section('title', 'Contact Inbox')
@section('content')
<div class="card"><div class="card-header"><h3 class="card-title">Tiket Kontak Masuk</h3></div>
<div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>ID</th><th>Nama</th><th>Topik</th><th>Pesan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@php $messages = $messages ?? \App\Models\ContactMessage::latest('id')->take(25)->get(); @endphp
@forelse($messages as $m)
<tr><td>{{ $m->id }}</td><td>{{ $m->name }}<div class="text-secondary">{{ $m->email }}</div></td><td><span class="badge">{{ $m->topic }}</span></td><td>{{ \Illuminate\Support\Str::limit($m->message, 80) }}</td>
<td><span class="badge bg-{{ $m->status === 'open' ? 'yellow' : ($m->status === 'spam' ? 'red' : 'green') }}">{{ $m->status }}</span></td>
<td>
<form method="POST" action="{{ route('admin.inbox.handle', $m->id) }}" style="display:inline">@csrf<input type="hidden" name="status" value="handled"><button class="btn btn-sm btn-success">Selesai</button></form>
<form method="POST" action="{{ route('admin.inbox.handle', $m->id) }}" style="display:inline">@csrf<input type="hidden" name="status" value="spam"><button class="btn btn-sm btn-danger">Spam</button></form>
</td></tr>
@empty
<tr><td colspan="6">@include('components.empty-admin', ['title' => 'Tidak ada tiket'])</td></tr>
@endforelse
</tbody></table></div>
@if(isset($messages) && method_exists($messages, 'links'))<div class="card-footer">{{ $messages->links() }}</div>@endif
</div>
@endsection
