@props(['icon' => 'cari', 'title' => 'Belum ada data', 'hint' => 'Coba lagi nanti.'])
@php
$paths = [
  'cari' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
  'hati' => '<path d="M12 21s-7.5-4.7-9.5-9C1 8.5 3 5 6.5 5c2 0 3.5 1 4.5 2.5C12 6 13.5 5 15.5 5 19 5 21 8.5 20.5 12c-2 4.3-8.5 9-8.5 9z"/>',
  'chat' => '<path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"/>',
  'bintang' => '<path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z"/>',
  'lonceng' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/>',
  'aman' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
  'kalender' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
  'baru' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
  'foto' => '<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13" r="3.5"/><path d="M8 7l1.5-3h5L16 7"/>',
  'cerah' => '<path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l2.5 2.5M16.5 16.5L19 19M19 5l-2.5 2.5M7.5 16.5L5 19"/>',
  'riwayat' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6"/>',
];
$svg = $paths[$icon] ?? null;
@endphp
<div class="jk-section" style="text-align:center;padding:36px 18px" role="status">
@if($svg)
<div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:#fff1f2;color:#f43f5e"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{!! $svg !!}</svg></div>
@else
<div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:#f4f4f5;color:#52525b;font-weight:800;font-size:22px" aria-hidden="true">{{ strtoupper(substr((string) $icon, 0, 1)) }}</div>
@endif
<div style="font-weight:800;margin-top:10px">{{ $title }}</div>
<div class="jk-muted">{{ $hint }}</div>
</div>
