# SCALE PLAN (social platform)

Arsitektur ini modular monolith Laravel — tetap, sampai data berkata lain.

| Tahap | Pemicu | Aksi |
|---|---|---|
| Sekarang | — | Index komposit (migrasi social graph), prime bulk, cache saran/trending, queue media+analitik+notif |
| 100K users | K1 search pain | Flip Scout→Meilisearch (wiring siap) |
| 10M baris messages/audit | K2 jenuh | Partisi waktu (sketsa di runbook) |
| 5K koneksi Reverb | K5 plafon | Multi-node + sticky LB (resep di DEPLOY) |
| Feed berat | p95 home > 800ms | Precompute fan-out untuk top creators + cursor pagination penuh |

Yang TIDAK dilakukan: microservices, Elasticsearch, transcode video
sync, cache data privat tanpa isolasi user, SELECT * di hot path.
