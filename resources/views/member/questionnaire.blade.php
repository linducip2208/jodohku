@extends('layouts.member')
@section('title', 'Kuesioner Kepribadian — Jodohku')
@section('content')
<h1 class="jk-h1">Kuesioner</h1><p class="jk-muted">Jawabanmu menajamkan skor kompatibilitas. Bisa diubah kapan pun.</p>
<div class="jk-section" id="qWrap">
<div class="jk-skeleton" style="height:120px;margin-bottom:10px"></div>
<p class="jk-muted"><span class="jk-spinner"></span> Memuat pertanyaan…</p>
</div>
<div id="qDone" class="jk-alert ok" style="display:none">Jawaban tersimpan. Rekomendasi dihitung ulang ✅</div>
<script>
(async function () {
  const wrap = document.getElementById('qWrap');
  try {
    const res = await fetch('/api/v1/questions', { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error('auth');
    const data = await res.json();
    const list = data.data || data;
    if (!list.length) { wrap.innerHTML = '<div class="jk-empty"><div class="big">🧠</div><p>Belum ada pertanyaan aktif.</p></div>'; return; }
    wrap.innerHTML = '<form id="qForm">' + list.map((q, i) => {
      const opts = (q.options || []).map(o =>
        `<label style="display:block;margin:6px 0"><input type="radio" name="q_${q.id}" value="${o.id}"> ${o.option_text || o.label || o.text}</label>`
      ).join('');
      const free = opts ? '' : `<input name="q_${q.id}_text" class="jk-input" style="width:100%" placeholder="Tulis jawabanmu">`;
      return `<div class="jk-section"><div class="jk-h2">${i + 1}. ${q.question_text || q.text}</div>${opts}${free}</div>`;
    }).join('') + '<button class="jk-submit" type="submit">Simpan Jawaban</button></form>';
    document.getElementById('qForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const answers = [];
      list.forEach(q => {
        const sel = document.querySelector(`input[name="q_${q.id}"]:checked`);
        const txt = document.querySelector(`input[name="q_${q.id}_text"]`);
        if (sel) answers.push({ question_id: q.id, question_option_id: parseInt(sel.value) });
        else if (txt && txt.value.trim()) answers.push({ question_id: q.id, answer_text: txt.value.trim() });
      });
      if (!answers.length) { window.jkToast && window.jkToast('Isi minimal satu jawaban', false); return; }
      const r = await fetch('/api/v1/questionnaire/answers', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' }, body: JSON.stringify({ answers }) });
      if (r.ok || r.status === 201) { document.getElementById('qDone').style.display = 'block'; window.scrollTo(0, 0); }
      else { window.jkToast && window.jkToast('Gagal menyimpan. Coba lagi.', false); }
    });
  } catch (err) {
    wrap.innerHTML = '<div class="jk-empty"><div class="big">🔒</div><p>Masuk untuk mengisi kuesioner.</p><a href="/login">Masuk →</a></div>';
  }
})();
</script>
@endsection
