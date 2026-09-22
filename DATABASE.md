# DATABASE — Grup Tabel & Constraint Kunci

Migrasi: `database/migrations/*`. Fresh seed: `php artisan migrate:fresh --seed`.

## users & profil (`080100`, `080200`)

- `users` unik: `email`, `phone`, `username`; index `account_type/role/status/gender/city+province/last_active_at`; soft delete.
- `profiles` 1:1 `user_id` unik; `profile_photos/videos` FK cascade; `profile_privacy` 1:1; `profile_views` index dua arah.

## preferensi & minat (`080300`)

- `interests` unik `name/slug`; `user_interests` unik `(user_id,interest_id)`; `partner_preferences` 1:1 `user_id`.

## kuesioner (`080400`)

- `question_categories` unik `key`; `questionnaire_versions` unik `version`; `questions` index `(category_key,is_active)`; `question_options` index `(question_id,sort_order)`; `questionnaire_answers` unik `(user_id,question_id,questionnaire_version_id)`.

## matching (`080500`)

- `likes` unik `(liker_id,liked_id)`; `matches` unik kanonis `(user_a_id,user_b_id)` + `ulid` unik, `like_id` nullable; `match_scores` unik `(user_id,candidate_id)`; `favorites` unik; `super_likes/boosts/rewinds` index waktu.

## chat (`080600`)

- `conversations` `ulid` unik, `match_id` nullable; `conversation_members` unik `(conversation_id,user_id)`; `messages` unik `(conversation_id,client_message_id)` (idempotensi) + `ulid` unik; `message_reactions/reads/deletions` unik komposit; `chat_requests/blocks/reports/labels/user_settings` index penerima/status.

## moderasi (`080700`)

- `profanity_categories` unik `name/slug`; `profanity_words` unik `word`; `moderation_rules` index `(is_active,priority)`; `reports` index status/pelapor; `blocks` unik `(blocker_id,blocked_id)`; `moderation_logs/queue` index polimorfik.

## verifikasi & fraud (`080800`)

- `verification_requests/documents`, `fraud_risk_scores` index `(user_id,scored_at)`, `fraud_events` index `(user_id,event_type)`.

## monetisasi (`080900`)

- `payment_gateways` unik `code`; `payment_gateway_settings` unik `(gateway,environment,key)`; `membership_plans` unik `code`; `subscriptions` index `(user_id,status)`; `payments` unik `invoice_number` + `gateway_transaction_id`; `payment_items` FK payment; `payment_webhooks` index `(gateway,is_processed)` + `payload.event_id` untuk idempotensi; `credit_products` unik `code`; `credit_wallets` unik `user_id`; `credit_transactions` index `(user_id,created_at)`.

## notifikasi & AI (`081000`)

- `notifications` (uuid), `notification_preferences` 1:1; `ai_providers` unik `code`; `ai_models` unik `code`; `ai_personalities` unik `code`; `ai_usage_logs` index user/conversation.

## virtual & operator (`081100` + `2026_09_21_000001`)

- `virtual_profiles` 1:1 `user_id`; `virtual_conversations` unik `(conversation_id,virtual_profile_id)` + `ai_paused_at` (takeover menjeda AI); `chat_triggers` index `(event,is_active)`; `chat_trigger_actions` index; `chat_templates` unik `code`; `operator_assignments` index operator/conversation + `is_active`.

## sosial & settings (`081200`)

- `gifts` unik `code`; `gift_transactions`; `events/slug` unik; `event_members` unik `(event_id,user_id)` + `event_members/groups/group_members/posts/comments/post_likes/ads/*` constraint standar; `settings` unik `(group,key)`; `audit_logs` index polimorfik.
- `events.latitude/longitude` decimal nullable (`2026_09_22_000002`, MySQL 8 + SQLite) untuk `nearby()`; `forum_threads.views_count` (`2026_09_22_000001`).

## kupon, blog & forum (`2026_09_21_000100`)

- `coupons` unik `code`; `coupon_redemptions` unik `(coupon_id,payment_id)` + index `(coupon_id,user_id)`; redeem tercatat dalam transaksi checkout yang sama (tanpa orphan payment).
- `blog_posts` unik `slug`, soft delete, index `(status,published_at)`.
- `forums` unik `slug`; `forum_threads` FK forum/user cascade, index `(forum_id,last_reply_at)`, counter `reply_count`; `forum_replies` FK thread cascade, index `(thread_id,id)`.
