# ADMIN — Jodohku Operational Dashboard

Layout Tabler terpisah (`layouts.admin`). Zona RBAC via Gates + Policies:

```text
staff       dashboard (semua staf)
moderator   users baca/suspend/verify, foto, verifikasi, reports/blocks,
            moderasi, fraud, chat view, community, inbox
operator    chat AI/virtual, trigger/schedule, queue/takeover/pause/resume
admin       users tulis/ban/kredit, matching config, plans/subs/payments/
            gateways (lihat/toggle), credits, kupon, gifts, boosts, ads,
            broadcast, analytics, AI usage, settings (lihat), audit
superadmin  kredensial gateway, settings tulis, feature flags
```

## Hal penting

- Users: pencarian + filter (`q/role/status/account_type/demo`) + badge Demo.
- Moderasi: antrean + kamus kata (`words/test` dry-run) + bust cache otomatis.
- Matching: bobot via `MatchingAdminController::weights` (tulis DB + bump
  `matchmaking.version` agar cache skor versi-lama invalid).
- Broadcast: `BroadcastMessage` (hormati opt-out promosi, queued).
- Settings: generik per group (`seo`, `chat`, `calls`, …) + audit; menyimpan
  grup `seo` otomatis bust cache sitemap/PSEO.
- Audit log: semua aksi penting tercatat tanpa sekret.
