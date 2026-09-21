<div class="jk-section" style="background:#faf5ff;border-color:#e9d5ff">
<div class="jk-h2">✨ AI Assistant</div>
@if(empty($suggestions))
<button class="jk-btn jk-btn-super" wire:click="load">Minta Saran Balasan</button>
@else
<div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">
@foreach($suggestions as $s)<button class="jk-input" style="text-align:left;cursor:pointer" wire:click="pick('{{ addslashes(\Illuminate\Support\Str::limit($s, 120)) }}')">{{ \Illuminate\Support\Str::limit($s, 120) }}</button>@endforeach
</div>
@endif
<div class="jk-muted" style="font-size:11px;margin-top:6px">Didukung AiChatAssistantService · klik saran untuk mengisi kolom chat.</div>
</div>
