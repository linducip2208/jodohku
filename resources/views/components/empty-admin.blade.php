@props(['title' => 'Belum ada data', 'hint' => 'Data akan muncul di sini.', 'ti' => 'ti-inbox'])
<div class="empty">
<div class="empty-icon"><i class="ti {{ $ti }}" style="font-size:32px"></i></div>
<p class="empty-title">{{ $title }}</p>
<p class="empty-subtitle text-secondary">{{ $hint }}</p>
</div>
