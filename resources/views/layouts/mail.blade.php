<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('subject', 'Jodohku')</title>
<style>body{font-family:system-ui,sans-serif;background:#f4f4f5;margin:0;padding:24px}.mail{max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden}.mail-head{background:linear-gradient(135deg,#f43f5e,#8b5cf6);color:#fff;padding:24px;font-weight:800;font-size:20px}.mail-body{padding:24px;color:#27272a;line-height:1.6}.mail-foot{padding:16px 24px;color:#71717a;font-size:12px;border-top:1px solid #f1f1f4}.btn{display:inline-block;background:#f43f5e;color:#fff!important;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:700}</style>
</head><body><div class="mail"><div class="mail-head">Jodohku</div><div class="mail-body">@yield('content')</div><div class="mail-foot">© {{ date('Y') }} Jodohku · Jika butuh bantuan balas email ini.</div></div></body></html>
