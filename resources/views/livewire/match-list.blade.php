<div>
@if($matches->isEmpty())
@include('components.empty', ['icon' => '💘', 'title' => 'Belum ada match', 'hint' => 'Saling like untuk match. Coba Discover!'])
@else
<div class="jk-grid">
@foreach($matches as $mt)
@php $me = auth()->id(); $otherId = $mt->user_a_id == $me ? $mt->user_b_id : $mt->user_a_id; $other = \App\Models\User::find($otherId); @endphp
@if($other) @include('components.profile-card', ['user' => $other, 'score' => $mt->score ?? null]) @endif
@endforeach
</div>
@endif
</div>
