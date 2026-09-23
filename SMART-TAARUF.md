# SMART TAARUF — Jodohku

Alur terpandu: `kenalan → taaruf → khitbah → (completed)`, didukung
`CourtshipService` (transaksi + row-lock anti double-advance, wajib match
aktif, khitbah wajib `guardian_approved_at`).

## Aturan penting

- Satu courtship aktif per pasangan (dicek + assuming DB wajar).
- Guardian: diisi satu pihak, **disetujui pihak lain** (anti self-approve;
  tercatat di `stage_history`).
- Chaperone: pihak ketiga, ditambah dari tahap taaruf, tercatat sebagai
  `ConversationMember` role `chaperone` (read-only secara sosial).
- Withdraw menutup courtship + notifikasi lawan (`CourtshipStageChanged`,
  didedup di `NotificationService`).

## Konselor & laporan

- `ConsultationService`: slot validasi future + durasi 15–180 mnt, clash-check
  portable lintas driver DB + `lockForUpdate`, state machine
  pending→confirmed→completed/cancelled.
- Berbagi laporan kompatibilitas eksplisit (`share_report` + milik sendiri);
  konselor hanya melihat report yang dibagikan untuk booking itu.
- Laporan kompatibilitas (`CompatibilityReportService`) grounded dari
  `MatchingEngine` — AI hanya asisten perangkai kata, bukan penentu jodoh.

## Konsumen

- Member: `/biro-jodoh/taaruf`, `/konselor`, `/konsultasi`, `/laporan`.
- AI: topik taaruf (`AiChatAssistantService::taarufTopics`), hanya dari data
  yang sudah visible kedua pihak.
- Demo: `jodohku:demo` membuat ~35% match menjadi courtship + 3 konselor demo.

## Penafian (lihat `/guidelines`)

Konselor = pendampingan umum, bukan nasihat medis/psikologis/hukum. Skor =
alat refleksi, bukan jaminan.
