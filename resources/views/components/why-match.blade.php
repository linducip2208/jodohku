@props(['why'])
@if(!empty($why))
<section class="jk-section" style="background:#fff7ed;border-color:#fed7aa" aria-labelledby="why-match">
<h2 class="jk-h2" id="why-match">Kenapa cocok? <span class="jk-pill premium">{{ (int) ($why['overall'] ?? 0) }}% · {{ $why['strength'] ?? '' }}</span></h2>
@if(!empty($why['reasons']))
<ul style="margin:8px 0;padding-left:18px">
@foreach($why['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach
</ul>
@endif
@if(!empty($why['dimensions']))
<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">
@foreach($why['dimensions'] as $d)
<div style="display:flex;align-items:center;gap:8px;font-size:12.5px">
<span style="width:92px;flex:none" class="jk-muted">{{ $d['label'] }}</span>
<div class="jk-progress" style="flex:1"><div style="width:{{ max(0, min(100, (int) $d['score'])) }}%"></div></div>
<span style="width:36px;text-align:right">{{ (int) $d['score'] }}%</span>
</div>
@endforeach
</div>
@endif
@if(!empty($why['cautions']))
<p class="jk-muted" style="font-size:12.5px;margin-top:8px">Perlu dibahas: {{ implode(' · ', $why['cautions']) }}</p>
@endif
<p class="jk-muted" style="font-size:11.5px">Dihitung transparan dari 8 dimensi MatchingEngine — AI hanya membantu menjelaskan, bukan memutuskan.</p>
</section>
@endif
