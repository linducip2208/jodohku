@props(['user', 'score' => null, 'compact' => false])
@php
$name = $user->display_name ?? $user->name ?? 'Member';
$age = method_exists($user, 'age') ? $user->age() : null;
$city = $user->city ?? null;
$photo = method_exists($user, 'avatarUrl') ? $user->avatarUrl() : ($user->avatar_path ?? null);
$initial = strtoupper(substr((string) $name, 0, 1));
@endphp
<div class="jk-card jk-profile-card">
<div class="jk-photo">
@if($photo)
<img src="{{ $photo }}" alt="Foto {{ $name }}" loading="lazy">
@else
<div class="jk-photo-fallback" aria-hidden="true">{{ $initial }}</div>
@endif
@if($score !== null)<span class="jk-compat">{{ (int) $score }}% cocok</span>@endif
<span class="{{ !empty($user->is_online) ? 'jk-online' : 'jk-offline' }}" title="{{ !empty($user->is_online) ? 'Online' : 'Offline' }}"></span>
</div>
<div class="jk-card-body">
<div class="jk-name">{{ $name }}{{ $age ? ', ' . $age : '' }}
@if(!empty($user->is_verified))<span class="jk-badge-verified" title="Terverifikasi" aria-label="Terverifikasi"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M8 12.5l2.7 2.7L16.5 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>@endif
@if(!empty($user->is_premium))<span class="jk-pill premium">PREMIUM</span>@endif
</div>
<div class="jk-meta">{{ $city ?? 'Indonesia' }}{{ isset($user->profile->occupation) && $user->profile->occupation ? ' · ' . $user->profile->occupation : '' }}</div>
@if(!$compact)
@livewire('like-buttons', ['userId' => $user->id], key('like-' . $user->id))
@endif
</div>
</div>
