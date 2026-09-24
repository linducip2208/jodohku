# RECOMMENDATION ARCHITECTURE

## Dua skor yang dipisahkan

- **compatibility** — HANYA dari `MatchingEngine::scorePair` (8 dimensi,
  tak tersentuh). Tetap satu-satunya otoritas skor dating.
- **social_score** — dari sinyal graph: minat bersama (×5), lingkaran
  sosial (×8), grup bersama (×10), kesamaan preferensi (×6).

`SocialDatingRecommendationService::recommend()` mengambil kandidat dari
`CandidateRetrievalService` (filter dating + blokir + incognito +
counselor), menghitung keduanya, merangking
`compatibility*0.7 + social_score*0.3`.

## Penjelasan aman (tidak deterministik)

Alasan yang dikembalikan hanya pola perilaku umum:

- "Memiliki beberapa minat yang sama"
- "Terhubung dengan lingkaran sosialmu"
- "Sering aktif di topik yang sama"
- "Preferensi dating kalian memiliki beberapa kesamaan"

DILARANG di semua permukaan: "dia jodohmu", persentase-sebagai-kepastian,
klaim "95% pasti cocok". Persentase kompatibilitas yang tampil berasal
dari MatchingEngine dan berlabel penjelasan (`MatchExplanation`).

## Dilarang memakai data sensitif

Tidak ada: isi pesan, lokasi persis, data verifikasi/fraud, skor internal
lain. Blokir/laporan/mute selalu mengecualikan kandidat di semua lapis.
