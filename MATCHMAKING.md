# MATCHMAKING — Pipeline, Bobot, Mutual

## Pipeline (`MatchingEngine`, `config/matchmaking.php`)

1. Hard filter: bukan diri sendiri, `status=active`, tidak diblokir; gender/age preference mengecilkan skor via `mutualGate` (0.35 bila dilanggar).
2. 8 dimensi (0–100): `age`, `location` (haversine vs `max_distance_km`), `preference` (agama/edu/marital/gender/verifikasi/foto berbobot importance), `personality` (kuesioner overlap), `interest` (Jaccard), `lifestyle` (smoking/drinking/height), `goal` (matriks kompatibilitas), `behavior` (online/aktif/kelengkapan/verifikasi/foto).
3. Bobot default 10/10/20/20/10/10/10/10 dinormalisasi 100; dua arah `a_to_b/b_to_a` → `mutual` rata-rata.
4. `persistScore` menyimpan `match_scores` direksional + baris kanonis; `DiscoveryService::discover` + `candidatesFor` memfilter lalu skor in-memory; boost hanya prioritas tampil.

## Mutual (`LikeService`, `Like`, `UserMatch`)

- `like(A,B)` → `firstOrCreate` like; `createsMatch` cek like balik → `UserMatch::canonical(min,max)` + `firstOrCreate` (unik `(user_a_id,user_b_id)`).
- Match baru: isi `compatibility_score`, fire `MutualMatchCreated` → notifikasi match. Unlike: hapus like, nonaktifkan match bila tak lagi mutual. Diuji idempoten di `MutualLikeCreatesMatchTest`.
