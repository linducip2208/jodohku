# CHAT — Alur, Moderasi, Realtime

## Alur (`ChatService`, `config/chat.php`)

1. `findOrCreateDirect(A,B)`: guard blokir/self, reuse `Conversation::findDirect`, buat + 2 `conversation_members`.
2. `sendMessage`: cek anggota + `is_blocked`, rate-limit (free 20/mnt, 200/jam; premium 60/1000), validasi panjang (`CHAT_MAX_LENGTH=2000`), idempotensi `(conversation_id,client_message_id)`.
3. Moderasi lokal (`MessageModerationService::moderate`): `block` → throw; selain itu simpan `body=clean` + `metadata.moderation{ risk,decision,flags }`.
4. Simpan attachments (maks 5), update `last_message_at`, fire `MessageSent/Received`, dispatch `SendChatNotification` (DB+mail) dan `ProcessMessageModeration` bila `needs_ai`.
5. `edit/react/delete/markRead/search/setting/typing` sesuai `ConversationPolicy/MessagePolicy`.

## Moderasi

- `ProfanityService` (DB `profanity_words`, cache 5 mnt) + `ScamDetectionService` (no HP, email, invite WA/Telegram/IG, shortener/IP-URL, investment, money_request, credential_phish, urgency).
- Keputusan: `risk≥85 block`, `≥60 flag`, `≥35 warning`, profanity>0 `mask`, else `allow`; `block/flag` masuk `moderation_queue` + `moderation_logs`.

## Realtime (Reverb)

- Broadcast `MessageSent/MessageReceived/TypingIndicator` ke `PrivateChannel(conversations.{id})`; `BROADCAST_CONNECTION=reverb`, auth via `routes/channels.php` + Sanctum.
