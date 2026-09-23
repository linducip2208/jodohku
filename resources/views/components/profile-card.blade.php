@props(['user', 'score' => null, 'compact' => false])
@php
$name = $user->display_name ?? $user->name ?? 'Member';
$age = method_exists($user, 'age') ? $user->age() : null;
$city = $user->city ?? null;
$photo = method_exists($user, 'avatarUrl') ? $user->avatarUrl() : ($user->avatar_path ?? null);
$initial = strtoupper(substr((string) $name, 0, 1));
$occupation = $user->profile->occupation ?? null;
$goal = $user->profile->relationship_goal ?? null;
if ($goal instanceof \BackedEnum) { $goal = $goal->value; }
$goalLabel = is_string($goal) && $goal !== '' ? ucwords(str_replace(['_', '-'], ' ', $goal)) : null;
$interestNames = collect($user->interests ?? [])->pluck('name')->filter()->take(3);
@endphp
<article class="jk-card jk-profile-card">
<div class="jk-photo">
<div class="jk-photo-fallback" aria-hidden="true">{{ $initial }}</div>
@if($photo)
<img src="{{ $photo }}" alt="Foto {{ $name }}" loading="lazy" onerror="this.remove()">
@endif
@if($score !== null)<span class="jk-compat">{{ (int) $score }}% cocok</span>@endif
<span class="{{ !empty($user->is_online) ? 'jk-online' : 'jk-offline' }}" title="{{ !empty($user->is_online) ? 'Online' : 'Offline' }}"></span>
</div>
<div class="jk-card-body">
<div class="jk-name"><a href="/profile/{{ $user->id }}" style="color:inherit;text-decoration:none">{{ $name }}</a>{{ $age ? ', ' . $age : '' }}
@if(!empty($user->is_verified))<span class="jk-badge-verified" title="Terverifikasi" aria-label="Terverifikasi"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M8 12.5l2.7 2.7L16.5 9" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>@endif
@if(!empty($user->is_premium))<span class="jk-pill premium">PREMIUM</span>@endif
</div>
<div class="jk-meta">{{ $city ?? 'Indonesia' }}{{ $occupation ? ' · ' . $occupation : '' }}</div>
@if($goalLabel && !$compact)<div class="jk-meta">Tujuan: {{ $goalLabel }}</div>@endif
@if($interestNames->isNotEmpty())
<div class="jk-tags" aria-label="Minat">@foreach($interestNames as $t)<span class="jk-tag">{{ $t }}</span>@endforeach</div>
@endif
@if(!$compact)
@livewire('like-buttons', ['userId' => $user->id], key('like-' . $user->id))
@else
<div style="margin-top:8px"><a class="jk-pill" href="/profile/{{ $user->id }}">Lihat profil</a></div>
@endif
</div>
</article>
