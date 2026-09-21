@props(['label', 'value', 'icon' => '📊', 'color' => ''])
<div class="col-sm-6 col-lg-3"><div class="card"><div class="card-body">
<div class="subheader">{{ $label }}</div>
<div class="h1 mb-1">{{ $value }}</div>
<div class="text-secondary" style="font-size:28px">{{ $icon }}</div>
</div></div></div>
