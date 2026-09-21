# ARCHITECTURE — Jodohku

```
Blade/Livewire ─┐
API /api/v1 ─────┼─▶ Controllers ─▶ Services ─▶ Models ─▶ MySQL/SQLite
Reverb WS ───────┘         │            ├─▶ Jobs/Events/Listeners (queue sync/redis)
                           └─▶ Policies/Gates (member→superadmin)
```

## Services (keputusan)

- `MatchingEngine`: 8 dimensi ternormalisasi 100 (`config/matchmaking.php`), hard filter blokir/status, boost hanya prioritas tampil (`DiscoveryService`).
- `LikeService` + `Like::createsMatch`: mutual → `UserMatch::canonical` + `firstOrCreate` (idempoten).
- `ChatService`: guard blokir, rate-limit premium-aware, moderasi lokal dulu, idempotensi `client_message_id`, broadcast `MessageSent/Received/TypingIndicator`.
- `MessageModerationService` (lokal, tanpa AI) → `ProfanityService` + `ScamDetectionService`; AI hanya via `ProcessMessageModeration` bila `needs_ai`.
- `VirtualMemberService`: cap 5/hari, jam 08–22, cooldown per-trigger, mode template/hybrid/ai, label transparan selalu ditempel.
- `OperatorService`: `takeover` → status `transferred` + `ai_paused_at=now()`; `resume` → `active` + `ai_paused_at=null`.
- `PaymentService` + `PaymentGatewayManager`: driver per-gateway, `handleWebhook` idempoten via `payload.event_id`, `fulfill` mengaktifkan subs/kredit sekali.
- `CreditService`: `lockForUpdate`, delta negatif ditolak (`Insufficient credits`).
- `AiService` + `AI/AiProviderManager`: OpenAI & compatible (`base_url/key/model`), `AiCostService`, `AiUsageLog`, rate-limit per-user.
- `SubscriptionService`/`MembershipService`, `GiftService`, `BoostService`, `VerificationService`, `FraudDetectionService`, `NotificationService`, `AuditService` (scrub sekret).

## Events/Listeners/Jobs

- `UserRegistered/ProfileLiked/ProfileViewed/MutualMatchCreated` → `FireVirtualTrigger` → `ProcessVirtualChatTrigger`.
- `MutualMatchCreated` → `SendMatchNotification`; chat → `SendChatNotification`, `ProcessMessageModeration`, `ProcessAiReply`, `RecalculateMatches`.
- Scheduler: `ExpireSubscriptions/Boost/Credits`, `GenerateDailyMatches`, `CleanupOldSessions`.

## Keamanan

Sanctum API + session web; Gate `member/premium/operator/moderator/admin/superadmin`; Policy `User/Conversation/Message/Payment/Report/VirtualConversation`; gateway sekret `Crypt` at-rest; audit tanpa sekret.
