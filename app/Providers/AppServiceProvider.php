<?php

namespace App\Providers;

use App\AI\AiProviderManager;
use App\Enums\UserRole;
use App\Events\MutualMatchCreated;
use App\Events\ProfileViewed;
use App\Events\UserRegistered;
use App\Listeners\AnalyticsListener;
use App\Listeners\FireVirtualTrigger;
use App\Listeners\LogAudit;
use App\Listeners\RecordProfileViewListener;
use App\Listeners\ReferralListener;
use App\Listeners\SendMatchNotification;
use App\Listeners\WarmNewUserMatches;
use App\Models\Brand;
use App\Models\Comment;
use App\Models\CompatibilityReport;
use App\Models\Consultation;
use App\Models\Conversation;
use App\Models\Courtship;
use App\Models\Group;
use App\Models\Message;
use App\Models\PartnerPreference;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\ProfilePhoto;
use App\Models\QuestionnaireAnswer;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use App\Models\UserInterest;
use App\Models\VirtualConversation;
use App\Observers\MatchRecalcObserver;
use App\Observers\PostObserver;
use App\Payments\PaymentGatewayManager;
use App\Policies\BrandPolicy;
use App\Policies\CommentPolicy;
use App\Policies\CompatibilityReportPolicy;
use App\Policies\ConsultationPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CourtshipPolicy;
use App\Policies\GroupPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PostPolicy;
use App\Policies\ReportPolicy;
use App\Policies\StoryPolicy;
use App\Policies\UserPolicy;
use App\Policies\VirtualConversationPolicy;
use App\Services\FaqService;
use App\Services\SeoService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected array $policies = [
        Brand::class => BrandPolicy::class,
        User::class => UserPolicy::class,
        Conversation::class => ConversationPolicy::class,
        Message::class => MessagePolicy::class,
        Payment::class => PaymentPolicy::class,
        Report::class => ReportPolicy::class,
        VirtualConversation::class => VirtualConversationPolicy::class,
        Post::class => PostPolicy::class,
        Comment::class => CommentPolicy::class,
        Story::class => StoryPolicy::class,
        Group::class => GroupPolicy::class,
        Courtship::class => CourtshipPolicy::class,
        Consultation::class => ConsultationPolicy::class,
        CompatibilityReport::class => CompatibilityReportPolicy::class,
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
        // Whitelabel brand managers: admins + clients bound to a brand.
        Gate::define('brand-manager', fn (User $u) => in_array($u->role, [UserRole::Admin, UserRole::Superadmin], true) || ($u->role === UserRole::Client && $u->brand_id !== null));
        // Any staff (operator/moderator/admin/superadmin) — read-only areas.
        Gate::define('staff', fn (User $u) => in_array($u->role, [UserRole::Operator, UserRole::Moderator, UserRole::Admin, UserRole::Superadmin], true));

        // Events (Laravel 13 convention: Event::listen in AppServiceProvider)
        Event::subscribe(FireVirtualTrigger::class);
        Event::subscribe(LogAudit::class);
        Event::subscribe(AnalyticsListener::class);
        Event::subscribe(ReferralListener::class);
        Event::listen(MutualMatchCreated::class, SendMatchNotification::class);
        // Scale P1: throttled profile-view writer + register-time warming.
        Event::listen(ProfileViewed::class, RecordProfileViewListener::class);
        Event::listen(UserRegistered::class, WarmNewUserMatches::class);

        // Homepage GEO schemas (WebSite + Organization + visible FAQPage).
        View::composer('welcome', function ($view) {
            try {
                $seo = app(SeoService::class);
                $faqs = app(FaqService::class)->homepage();
                $schemas = [];
                if ($seo->site('schema_enabled', true)) {
                    $schemas[] = $seo->websiteSchema();
                    $schemas[] = $seo->organizationSchema();
                    $schemas[] = $seo->faqSchema($faqs);
                }
                $view->with('seoSchemas', $schemas);
            } catch (\Throwable) {
            }
        });
        // Match recalculation on data change (not on profile views).
        foreach ([Profile::class, PartnerPreference::class, ProfilePhoto::class, UserInterest::class, QuestionnaireAnswer::class, User::class] as $model) {
            $model::observe(MatchRecalcObserver::class);
        }
        Post::observe(PostObserver::class);
    }
}
