@extends('layouts.member')
@section('title', 'Referral — Jodohku')
@section('content')
<h1 class="jk-h1">Ajak teman, dapat kredit</h1>
<p class="jk-muted">Teman daftar lewat link-mu + berlangganan → kamu dapat kredit otomatis.</p>
@if(session('status'))<div class="jk-alert ok">{{ session('status') }}</div>@endif
<div class="jk-section"><div class="jk-h2">Link referralmu</div>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
<input id="ref-link" class="jk-input" style="flex:1;min-width:200px" readonly value="{{ $stats['link'] }}" aria-label="Link referral" onclick="this.select()">
<button class="jk-btn jk-btn-like" type="button" onclick="navigator.clipboard?.writeText(document.getElementById('ref-link').value).then(()=>window.jkToast?.('Link disalin ✅')).catch(()=>document.getElementById('ref-link').select())">Salin</button>
<a class="jk-btn" style="text-decoration:none" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode('Gabung Jodohku lewat linkku: '.$stats['link']) }}">WA</a>
<a class="jk-btn" style="text-decoration:none" target="_blank" rel="noopener" href="https://x.com/intent/tweet?text={{ urlencode('Gabung Jodohku lewat linkku: '.$stats['link']) }}">X</a>
</div>
<div class="jk-muted" style="font-size:13px;margin-top:8px">{{ $stats['referred'] }} mendaftar · {{ $stats['converted'] }} berlangganan · {{ $stats['rewards_earned'] }} kredit diperoleh</div>
</div>
<div class="jk-section"><div class="jk-h2">Riwayat</div>
@forelse($history as $h)<div class="jk-story" style="margin-bottom:8px"><div style="flex:1"><strong>{{ $h->referred?->displayName() ?? 'Member' }}</strong><div class="jk-muted">{{ $h->status }} · {{ $h->created_at?->diffForHumans() }}</div></div></div>
@empty @include('components.empty', ['icon' => 'orang', 'title' => 'Belum ada referral', 'hint' => 'Bagikan link di atas ke temanmu.']) @endforelse
</div>
<div class="jk-section"><div class="jk-h2">Jadi afiliator</div>
@if($account)
<p class="jk-muted" style="margin:0">Kode <strong>{{ $account->code }}</strong> · status {{ $account->status }} · saldo komisi Rp{{ number_format($stats['affiliate_balance'], 0, ',', '.') }}</p>
@else
<p class="jk-muted">Dapatkan komisi dari pembayaran member yang kamu ajak (persetujuan admin).</p>
<form method="POST" action="/referral/affiliate">@csrf<button class="jk-btn jk-btn-like" type="submit">Ajukan afiliasi</button></form>
@endif
</div>
@endsection
