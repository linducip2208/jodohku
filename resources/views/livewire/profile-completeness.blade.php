<div class="jk-section">
<div style="display:flex;justify-content:space-between;align-items:center"><strong>Kelengkapan Profil</strong><strong>{{ $pct }}%</strong></div>
<div class="jk-progress" style="margin-top:8px"><div style="width:{{ $pct }}%"></div></div>
@if($tips)<ul class="jk-muted" style="margin:8px 0 0;padding-left:18px;font-size:13px">@foreach($tips as $t)<li>{{ $t }}</li>@endforeach</ul>@endif
</div>
