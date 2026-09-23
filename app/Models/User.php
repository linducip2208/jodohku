<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\Gender;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'account_type',
        'role',
        'status',
        'username',
        'display_name',
        'date_of_birth',
        'gender',
        'is_verified',
        'is_premium',
        'is_demo',
        'is_online',
        'last_active_at',
        'latitude',
        'longitude',
        'city',
        'province',
        'country',
        'avatar_path',
        'profile_completion',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'is_verified' => 'boolean',
            'is_premium' => 'boolean',
            'is_demo' => 'boolean',
            'is_online' => 'boolean',
            'last_active_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'profile_completion' => 'integer',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->username) && ! empty($user->email)) {
                $base = Str::slug(Str::before($user->email, '@'));
                $user->username = $base.'-'.Str::lower(Str::random(4));
            }
            $user->account_type ??= AccountType::Real;
            $user->role ??= UserRole::Member;
            $user->status ??= UserStatus::Active;
        });
    }

    // Relations

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function partnerPreference(): HasOne
    {
        return $this->hasOne(PartnerPreference::class);
    }

    public function profilePrivacy(): HasOne
    {
        return $this->hasOne(ProfilePrivacy::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProfilePhoto::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProfileVideo::class);
    }

    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interests')->withTimestamps();
    }

    public function userInterests(): HasMany
    {
        return $this->hasMany(UserInterest::class);
    }

    public function likesGiven(): HasMany
    {
        return $this->hasMany(Like::class, 'liker_id');
    }

    public function likesReceived(): HasMany
    {
        return $this->hasMany(Like::class, 'liked_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function superLikesGiven(): HasMany
    {
        return $this->hasMany(SuperLike::class, 'sender_id');
    }

    public function boosts(): HasMany
    {
        return $this->hasMany(Boost::class);
    }

    public function matchesA(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user_a_id');
    }

    public function matchesB(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user_b_id');
    }

    public function conversationMembers(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_members')->withTimestamps();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditWallet(): HasOne
    {
        return $this->hasOne(CreditWallet::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    public function reportsMade(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function reportsReceived(): HasMany
    {
        return $this->hasMany(Report::class, 'reported_user_id');
    }

    public function blocksInitiated(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_id');
    }

    public function blocksReceived(): HasMany
    {
        return $this->hasMany(Block::class, 'blocked_id');
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function questionnaireAnswers(): HasMany
    {
        return $this->hasMany(QuestionnaireAnswer::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function operatorAssignments(): HasMany
    {
        return $this->hasMany(OperatorAssignment::class, 'operator_id');
    }

    public function giftTransactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'sender_id');
    }

    // Scopes

    /** @param Builder<User> $query */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Real);
    }

    /** @param Builder<User> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }

    /** @param Builder<User> $query */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('is_online', true);
    }

    /** @param Builder<User> $query */
    public function scopePremium(Builder $query): Builder
    {
        return $query->where('is_premium', true);
    }

    /** @param Builder<User> $query */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    // Helpers

    public function age(): ?int
    {
        if (! $this->date_of_birth) {
            return null;
        }

        $dob = $this->date_of_birth instanceof CarbonInterface
            ? $this->date_of_birth
            : now()->parse($this->date_of_birth);

        return (int) $dob->diffInYears(now());
    }

    public function isReal(): bool
    {
        return $this->account_type === AccountType::Real;
    }

    public function isVirtual(): bool
    {
        return $this->account_type === AccountType::Virtual;
    }

    public function isAi(): bool
    {
        return $this->account_type === AccountType::Ai;
    }

    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Superadmin], true);
    }

    public function canChat(): bool
    {
        return $this->status === UserStatus::Active && ! $this->trashed();
    }

    /** Human-readable login block reason, or null when login is allowed. */
    public function loginBlockedReason(): ?string
    {
        return match ($this->status) {
            UserStatus::Active, UserStatus::PendingVerification => null,
            UserStatus::Suspended => 'Akun ditangguhkan sementara. Hubungi dukungan.',
            UserStatus::Banned => 'Akun diblokir permanen. Hubungi dukungan untuk banding.',
            UserStatus::Inactive => 'Akun nonaktif. Hubungi dukungan untuk mengaktifkan kembali.',
            UserStatus::Deleted => 'Akun telah dihapus.',
            default => 'Akun tidak dapat digunakan.',
        };
    }

    public function isPremium(): bool
    {
        if ($this->is_premium) {
            return true;
        }

        return $this->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    /** Single source of truth for entitlement: valid subscription only. Cached column is synced async. */
    public function hasValidSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    public function creditBalance(): int
    {
        return $this->creditWallet?->balance ?? 0;
    }

    public function displayName(): string
    {
        return $this->display_name ?: $this->name;
    }

    /** Public URL for avatar (or first photo fallback). Null when none. */
    public function avatarUrl(): ?string
    {
        $path = $this->avatar_path;
        if (! $path) {
            // Avatar is inherently public: never fall back to a private or
            // unapproved photo (prevents private-photo URL disclosure).
            $photo = $this->relationLoaded('photos')
                ? $this->photos->first()
                : $this->photos()->ordered()->where('status', 'approved')->where('is_private', false)->first();
            $path = $photo?->path;
        }
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
