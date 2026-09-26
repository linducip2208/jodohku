@props(['height' => 28])
@php
$theme = $brandTheme ?? ['name' => 'Jodohku', 'primary' => '#f43f5e', 'secondary' => '#8b5cf6', 'logo' => null];
$name = (string) ($theme['name'] ?? 'Jodohku');
$primary = (string) ($theme['primary'] ?? '#f43f5e');
$secondary = (string) ($theme['secondary'] ?? '#8b5cf6');
// Split name in two for the signature gradient look (Jodoh|ku style:
// last 2 chars gradient for longer names, half for short ones).
$len = mb_strlen($name);
$cut = $len > 4 ? $len - 2 : (int) ceil($len / 2);
$first = mb_substr($name, 0, $cut);
$rest = mb_substr($name, $cut);
@endphp
@if(!empty($theme['logo']))
<img src="{{ $theme['logo'] }}" alt="{{ $name }}" style="height:{{ (int) $height }}px;width:auto" loading="lazy">
@else
<span style="font-weight:800;font-size:inherit;letter-spacing:-.5px">{{ $first }}<span style="background:linear-gradient(135deg,{{ $primary }},{{ $secondary }});-webkit-background-clip:text;background-clip:text;color:transparent">{{ $rest }}</span></span>
@endif
