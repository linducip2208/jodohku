# PAYMENT — Gateway, Webhook, Idempotensi

## Gateway (`config/payments.php`, `app/Payments/*`)

- Default `PAYMENT_GATEWAY=midtrans`, currency `IDR`; prioritas ipaymu(10) → xendit(20) → midtrans(30) → tripay(40).
- Kredensial dari env (`*_BASE_URL/API_KEY/...`); sekret webhook: `IPAYMU_CALLBACK_SECRET`, `XENDIT_WEBHOOK_TOKEN`, Midtrans via `MIDTRANS_SERVER_KEY` (sha512), `TRIPAY_PRIVATE_KEY/CALLBACK_SECRET`.
- Sekret DB (`payment_gateway_settings`) terenkripsi `Crypt` via `Admin\GatewayController@store`; baca transparan didekripsi (`PaymentGatewaySetting::value`), legacy plaintext tetap terbaca.

## Checkout (`PaymentService::checkout`)

1. Resolve plan (`membership_plans.code`) / produk kredit (`credit_products.code`) → hitung `amount`.
2. Buat `payments` (pending, `invoice_number` unik) + `payment_items`.
3. Panggil driver `createPayment` → simpan `gateway_response`, kembalikan `checkout_url`.

## Webhook truth (`POST /api/v1/webhooks/{gateway}` → `WebhookController` → `handleWebhook`)

- Validasi di driver: iPaymu HMAC-sha256 body, Xendit `x-callback-token`, Midtrans `sha512(order_id+status_code+gross_amount+server_key)`, Tripay HMAC `private_key`.
- Normalisasi → `reference` (invoice), `status` (`paid`/…), `event_id` (`gateway:ref:status`).
- Idempotensi: cari `payment_webhooks` via `payload->event_id`; bila `is_processed` → kembalikan tanpa efek. `fulfill` hanya bila payment belum `paid`.
- `fulfill`: `payments.status=paid`, aktifkan subscription (`SubscriptionService::activate`), tambah kredit (`CreditService::record` purchase), audit + event `PaymentPaid`.

Replay `event_id` sama → satu aktivasi (diuji di `PaymentWebhookPaidActivatesSubscriptionTest`).
