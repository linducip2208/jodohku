@props(['items' => [], 'title' => 'Pertanyaan umum'])
<section class="ld-section"><div class="ld-wrap ld-faq">
<h2 class="ld-h2">{{ $title }}</h2>
@foreach($items as $f)
<details><summary><strong>{{ $f['q'] }}</strong></summary><p class="ld-muted">{{ $f['a'] }}</p></details>
@endforeach
</div></section>
