@extends('layouts.member')
@section('title', ($profileUser->displayName() ?? 'Profil') . ' — Jodohku')
@section('content')
@php
$explain = null;
try {
  $me = auth()->user();
  if ($me && $me->id !== $profileUser->id) { $explain = app(\App\Services\MatchingEngine::class)->explain($me, $profileUser); }
} catch (\Throwable) {}
$photos = $profileUser->photos ?? collect();
@endphp
<a href="/discover" class="jk-muted">← Kembali</a>
<div class="jk-card" style="margin-top:8px">
<div class="jk-photo" style="aspect-ratio:4/4">
@if($profileUser->avatar_path)<img src="{{ $profileUser->avatar_path }}" alt="">@else<div class="jk-photo-fallback">{{ strtoupper(substr((string)($profileUser->displayName() ?? '?'),0,1)) }}</div>@endif
<span class="{{ $profileUser->is_online ? 'jk-online' : 'jk-offline' }}"></span>
@if(isset($score))<span class="jk-compat">{{ (int) $score }}% cocok</span>@endif
</div>
<div class="jk-card-body">
<div class="jk-name" style="font-size:20px">{{ $profileUser->displayName() }}{{ $profileUser->age() ? ', ' . $profileUser->age() : '' }} @if($profileUser->is_verified)<span class="jk-badge-verified">✔</span>@endif</div>
<div class="jk-muted">{{ $profileUser->city ?? 'Indonesia' }} · {{ $profileUser->is_online ? '● Online' : 'Terakhir aktif ' . ($profileUser->last_active_at?->diffForHumans() ?? '—') }}</div>
@if($profileUser->profile?->headline)<p><strong>{{ $profileUser->profile->headline }}</strong></p>@endif
@if($profileUser->profile?->bio)<p class="jk-muted">{{ $profileUser->profile->bio }}</p>@endif
<div class="jk-tags">
@if($profileUser->profile?->occupation)<span class="jk-tag">💼 {{ $profileUser->profile->occupation }}</span>@endif
@if($profileUser->profile?->education)<span class="jk-tag">🎓 {{ $profileUser->profile->education }}</span>@endif
@if($profileUser->profile?->height_cm)<span class="jk-tag">📏 {{ $profileUser->profile->height_cm }} cm</span>@endif
@foreach(($profileUser->interests ?? collect())->take(6) as $in)<span class="jk-tag">{{ $in->name }}</span>@endforeach
</div>
@livewire('like-buttons', ['userId' => $profileUser->id], key('profile-like-' . $profileUser->id))
</div>
</div>
@if($photos->count())
<div class="jk-section"><div class="jk-h2">📷 Galeri ({{ $photos->count() }})</div>
<div class="jk-grid" style="grid-template-columns:repeat(3,1fr)">@foreach($photos as $ph)<div class="jk-card"><div class="jk-photo" style="aspect-ratio:1/1">@if(!empty($ph->path) || !empty($ph->url))<img src="{{ $ph->path ?? $ph->url }}" alt="" loading="lazy">@else<div class="jk-photo-fallback">📷</div>@endif</div></div>@endforeach</div>
</div>
@endif
@if($explain)
<div class="jk-section" style="background:#fff7ed;border-color:#fed7aa"><div class="jk-h2">💡 Kenapa cocok?</div>
<p class="jk-muted">Skor mutual <strong>{{ $explain['mutual'] ?? $explain['score'] ?? '—' }}%</strong> — dihitung dari 8 dimensi MatchingEngine.</p>
@if(!empty($explain['common']))<div><strong>Kesamaan:</strong><ul>@foreach((array)$explain['common'] as $c)<li>{{ is_string($c) ? $c : json_encode($c) }}</li>@endforeach</ul></div>@endif
@if(!empty($explain['differences']))<div><strong>Perbedaan:</strong><ul>@foreach((array)$explain['differences'] as $d)<li>{{ is_string($d) ? $d : json_encode($d) }}</li>@endforeach</ul></div>@endif
@if(!empty($explain['breakdown']))<table class="jk-table"><thead><tr><th>Dimensi</th><th>Skor</th></tr></thead><tbody>@foreach((array)$explain['breakdown'] as $k => $v)<tr><td>{{ $k }}</td><td>{{ is_numeric($v) ? (int)$v : json_encode($v) }}</td></tr>@endforeach</tbody></table>@endif
</div>
@endif
@endsection
