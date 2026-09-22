<?php

namespace App\Providers;

use App\AI\AiProviderManager;
use App\Enums\UserRole;
use App\Events\MutualMatchCreated;
use App\Events\ProfileViewed;
use App\Listeners\FireVirtualTrigger;
use App\Listeners\LogAudit;
use App\Listeners\RecalcMatchesOnProfileUpdate;
use App\Listeners\SendMatchNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Models\VirtualConversation;
use App\Payments\PaymentGatewayManager;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
use App\Policies\VirtualConversationPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected array $policies = [
        User::class => UserPolicy::class,
        Conversation::class => ConversationPolicy::class,
        Message::class => MessagePolicy::class,
        Payment::class => PaymentPolicy::class,
        Report::class => ReportPolicy::class,
        VirtualConversation::class => VirtualConversationPolicy::class,
    ];

    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(AiProviderManager::class);
    }

    public function boot(): void
    {
        // Policies (Laravel 13: no EventServiceProvider/AuthServiceProvider by default)
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Role gates — Admin is NOT auto-superadmin
        Gate::define('member', fn (User $u) => in_array($u->role, [UserRole::Member, UserRole::Premium, UserRole::Operator, UserRole::Moderator, UserRole::Admin, UserRole::Superadmin], true));
        Gate::define('premium', fn (User $u) => $u->isPremium() || $u->role === UserRole::Premium);
        Gate::define('operator', fn (User $u) => in_array($u->role, [UserRole::Operator, UserRole::Admin, UserRole::Superadmin], true));
        Gate::define('moderator', fn (User $u) => in_array($u->role, [UserRole::Moderator, UserRole::Admin, UserRole::Superadmin], true));
        Gate::define('admin', fn (User $u) => in_array($u->role, [UserRole::Admin, UserRole::Superadmin], true));
        Gate::define('superadmin', fn (User $u) => $u->role === UserRole::Superadmin);
        // Any staff (operator/moderator/admin/superadmin) — read-only areas.
        Gate::define('staff', fn (User $u) => in_array($u->role, [UserRole::Operator, UserRole::Moderator, UserRole::Admin, UserRole::Superadmin], true));

        // Events (Laravel 13 convention: Event::listen in AppServiceProvider)
        Event::subscribe(FireVirtualTrigger::class);
        Event::subscribe(LogAudit::class);
        Event::listen(MutualMatchCreated::class, SendMatchNotification::class);
        Event::listen(ProfileViewed::class, RecalcMatchesOnProfileUpdate::class);
    }
}
