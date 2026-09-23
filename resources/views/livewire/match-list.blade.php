<div>
@if($matches->isEmpty())
@include('components.empty', ['icon' => 'hati', 'title' => 'Belum ada match', 'hint' => 'Saling like untuk match. Coba Discover!'])
@else
<div class="jk-grid">
@foreach($matches as $mt)
@php $me = auth()->id(); $other = (int) $mt->user_a_id === (int) $me ? $mt->userB : $mt->userA; @endphp
@if($other) @include('components.profile-card', ['user' => $other, 'score' => $mt->compatibility_score ?? null]) @endif
@endforeach
</div>
@endif
</div>
