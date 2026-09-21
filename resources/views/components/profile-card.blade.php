@props(['user', 'score' => null, 'compact' => false])
@php
$name = $user->display_name ?? $user->name ?? 'Member';
$age = method_exists($user, 'age') ? $user->age() : null;
$city = $user->city ?? null;
$photo = $user->avatar_path ?? ($user->photos->first()->path ?? $user->photos->first()->url ?? null);
$initial = strtoupper(substr((string) $name, 0, 1));
@endphp
<div class="jk-card jk-profile-card">
<div class="jk-photo">
@if($photo)
<img src="{{ $photo }}" alt="{{ $name }}" loading="lazy">
@else
<div class="jk-photo-fallback">{{ $initial }}</div>
@endif
@if($score !== null)<span class="jk-compat">{{ (int) $score }}% cocok</span>@endif
<span class="{{ !empty($user->is_online) ? 'jk-online' : 'jk-offline' }}"></span>
</div>
<div class="jk-card-body">
<div class="jk-name">{{ $name }}{{ $age ? ', ' . $age : '' }}
@if(!empty($user->is_verified))<span class="jk-badge-verified" title="Terverifikasi">✔</span>@endif
@if(!empty($user->is_premium))<span class="jk-pill premium">PREMIUM</span>@endif
</div>
<div class="jk-meta">{{ $city ?? 'Indonesia' }}{{ isset($user->profile->occupation) && $user->profile->occupation ? ' · ' . $user->profile->occupation : '' }}</div>
@if(!$compact)
@livewire('like-buttons', ['userId' => $user->id], key('like-' . $user->id))
@endif
</div>
</div>
