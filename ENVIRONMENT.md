# ENVIRONMENT — Variabel `.env`

Sumber kebenaran: `.env.example`. Semua sekret hanya placeholder — jangan commit sekret asli.

| Grup | Var | Default | Dipakai di |
|---|---|---|---|
| APP | `APP_NAME/ENV/KEY/DEBUG/URL/TIMEZONE` | Jodohku/local/-/true/localhost | `config/app.php` |
| DB | `DB_CONNECTION/DATABASE/HOST/PORT/USERNAME/PASSWORD` | sqlite/file | `config/database.php` |
| REDIS | `REDIS_HOST/PORT/PASSWORD/DB/CACHE_DB/URL/CLIENT` | 127.0.0.1:6379 | queue/cache/reverb scaling |
| MAIL | `MAIL_MAILER/HOST/PORT/USERNAME/PASSWORD/FROM_*` | log/127.0.0.1:2525 | `config/mail.php`, notifikasi |
| STORAGE | `FILESYSTEM_DISK/AWS_*` | local/kosong | `config/filesystems.php` (local + s3-compatible via `AWS_ENDPOINT`) |
| REVERB | `REVERB_APP_ID/KEY/SECRET/HOST/PORT/SCHEME` | kosong/localhost:8080/http | `config/broadcasting.php`, `config/reverb.php` |
| AI | `AI_ENABLED/PROVIDER/MODEL/BASE_URL/API_KEY`, `OPENAI_*`, `AI_RATE_*` | true/openai/gpt-4o-mini | `config/ai.php` |
| PAYMENT | `PAYMENT_GATEWAY/CURRENCY`, `IPAYMU_*`, `XENDIT_*`, `MIDTRANS_*`, `TRIPAY_*` (+ `*_WEBHOOK_TOKEN/SECRET`) | midtrans/IDR | `config/payments.php` |
| FEATURES | `FEATURE_AI/VIRTUAL/VIDEO_CALL/COMMUNITY/CREDITS/GIFTS/BOOST/ADS/VERIFICATION`, `FREE_DAILY_LIKES` | true/20 | `config/jodohku.php` |
| CHAT | `CHAT_MSG_PER_MIN/HOUR`, `CHAT_PREMIUM_*`, `CHAT_MAX_LENGTH` | 20/200/2000 | `config/chat.php` |
| MATCH | `MATCH_WEIGHT_*`, `MATCH_MAX_DISTANCE_KM/MIN_DAILY_SCORE/DAILY_PICKS` | lihat `.env.example` | `config/matchmaking.php` |
| SEO | `SEO_SITE_NAME/DEFAULT_TITLE/DESCRIPTION/CANONICAL_URL/ROBOTS_INDEX` | Jodohku/... | `config/seo.php` |
| SANCTUM | `SANCTUM_STATEFUL_DOMAINS/TOKEN_PREFIX` | localhost... | `config/sanctum.php` |

Kredensial gateway/AI dibaca dari env, disimpan terenkripsi (`Crypt`) di `payment_gateway_settings` via `Admin\GatewayController`.
