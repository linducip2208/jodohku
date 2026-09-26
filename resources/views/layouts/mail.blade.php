<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('subject', ($brandTheme['name'] ?? 'Jodohku'))</title>
@php
try {
    $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: null;
    $brandTheme = $brandTheme ?? app(\App\Services\BrandService::class)->theme(app(\App\Services\BrandService::class)->current($host));
} catch (\Throwable) {
    $brandTheme = $brandTheme ?? ['name' => 'Jodohku', 'primary' => '#f43f5e', 'secondary' => '#8b5cf6', 'logo' => null];
}
@endphp
<style>body{font-family:system-ui,sans-serif;background:#f4f4f5;margin:0;padding:24px}.mail{max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden}.mail-head{background:linear-gradient(135deg,{{ $brandTheme['primary'] ?? '#f43f5e' }},{{ $brandTheme['secondary'] ?? '#8b5cf6' }});color:#fff;padding:24px;font-weight:800;font-size:20px}.mail-body{padding:24px;color:#27272a;line-height:1.6}.mail-foot{padding:16px 24px;color:#71717a;font-size:12px;border-top:1px solid #f1f1f4}.btn{display:inline-block;background:{{ $brandTheme['primary'] ?? '#f43f5e' }};color:#fff!important;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:700}</style>
</head><body><div class="mail"><div class="mail-head">@if(!empty($brandTheme['logo']))<img src="{{ $brandTheme['logo'] }}" alt="{{ $brandTheme['name'] }}" style="height:28px;width:auto;vertical-align:middle"> @endif{{ $brandTheme['name'] ?? 'Jodohku' }}</div><div class="mail-body">@yield('content')</div><div class="mail-foot">© {{ date('Y') }} {{ $brandTheme['name'] ?? 'Jodohku' }} · Jika butuh bantuan balas email ini.</div></div></body></html>
