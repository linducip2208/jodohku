@extends('layouts.admin')
@section('title', 'Chat Settings')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
<div class="col"><h2 class="page-title"><i class="ti ti-message-cog me-1"></i> Pengaturan Chat</h2>
<div class="text-secondary">Semua nilai di bawah ini langsung dibaca oleh backend. Perubahan tercatat di audit log.</div></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('admin.settings.update') }}">
@csrf
@foreach($defs as $section => $fields)
<div class="card mb-3"><div class="card-header"><h3 class="card-title">{{ $section }}</h3></div>
<div class="card-body">
@foreach($fields as $f)
<div class="mb-3"><label class="form-label" for="fld-{{ $f['key'] }}">{{ $f['label'] }}</label>
@if(($f['type'] ?? '') === 'checkbox')
<input type="hidden" name="settings[{{ $f['key'] }}]" value="0">
<label class="form-check form-switch"><input class="form-check-input" type="checkbox" id="fld-{{ $f['key'] }}" name="settings[{{ $f['key'] }}]" value="1" @checked(!empty($values[$f['key']]))><span class="form-check-label">Aktif</span></label>
@else
<input class="form-control" type="number" min="0" id="fld-{{ $f['key'] }}" name="settings[{{ $f['key'] }}]" value="{{ $values[$f['key']] }}">
@endif
<div class="form-hint">{{ $f['hint'] ?? '' }}</div></div>
@endforeach
</div></div>
@endforeach
<div class="card"><div class="card-footer text-end"><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i> Simpan Pengaturan</button></div></div>
</form>
</div></div>
@endsection
