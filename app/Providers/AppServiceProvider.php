<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\PlatformRules\BlueskyRules;
use App\Ai\PlatformRules\FacebookRules;
use App\Ai\PlatformRules\InstagramRules;
use App\Ai\PlatformRules\LinkedInRules;
use App\Ai\PlatformRules\MastodonRules;
use App\Ai\PlatformRules\PinterestRules;
use App\Ai\PlatformRules\Registry;
use App\Ai\PlatformRules\ThreadsRules;
use App\Ai\PlatformRules\TikTokRules;
use App\Ai\PlatformRules\XRules;
use App\Ai\PlatformRules\YouTubeRules;
use App\Ai\Providers\ExtendedGeminiProvider;
use App\Ai\Tools\AttachmentCollector;
use App\Enums\SocialAccount\Platform;
use App\Listeners\StripeEventListener;
use App\Models\Account;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\Invite;
use App\Models\Media;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHashtag;
use App\Models\WorkspaceLabel;
use App\Socialite\InstagramProvider;
use App\Socialite\LinkedInPageExtendSocialite;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Ai;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Pennant\Feature;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use PostHog\PostHog;
use SocialiteProviders\Facebook\FacebookExtendSocialite;
use SocialiteProviders\LinkedIn\LinkedInExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Pinterest\PinterestExtendSocialite;
use SocialiteProviders\TikTok\TikTokExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->scoped(AttachmentCollector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAi();
        $this->configurePlatformRules();
        $this->configurePostHog();
        $this->configureRateLimiting();
        $this->configureSocialite();
        $this->configureStripeWebhooks();

        Cashier::useCustomerModel(Account::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        Feature::resolveScopeUsing(fn () => auth()->user()?->account);
        Feature::useMorphMap();
        Feature::discover();
    }

    protected function configureAi(): void
    {
        Ai::extend('gemini', function ($app, array $config) {
            return new ExtendedGeminiProvider(
                new GeminiGateway($app['events']),
                $config,
                $app->make(Dispatcher::class),
            );
        });
    }

    protected function configurePlatformRules(): void
    {
        $map = [
            Platform::Instagram->value => InstagramRules::class,
            Platform::InstagramFacebook->value => InstagramRules::class,
            Platform::Facebook->value => FacebookRules::class,
            Platform::X->value => XRules::class,
            Platform::TikTok->value => TikTokRules::class,
            Platform::YouTube->value => YouTubeRules::class,
            Platform::LinkedIn->value => LinkedInRules::class,
            Platform::LinkedInPage->value => LinkedInRules::class,
            Platform::Threads->value => ThreadsRules::class,
            Platform::Pinterest->value => PinterestRules::class,
            Platform::Bluesky->value => BlueskyRules::class,
            Platform::Mastodon->value => MastodonRules::class,
        ];

        foreach ($map as $value => $class) {
            Registry::register(
                Platform::from($value),
                $class,
            );
        }
    }

    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'account' => Account::class,
            'aiMessage' => AiMessage::class,
            'aiUsageLog' => AiUsageLog::class,
            'invite' => Invite::class,
            'media' => Media::class,
            'notification' => Notification::class,
            'plan' => Plan::class,
            'notificationPreference' => NotificationPreference::class,
            'post' => Post::class,
            'postComment' => PostComment::class,
            'postPlatform' => PostPlatform::class,
            'socialAccount' => SocialAccount::class,
            'subscription' => Subscription::class,
            'subscriptionItem' => SubscriptionItem::class,
            'user' => User::class,
            'workspace' => Workspace::class,
            'workspaceHashtag' => WorkspaceHashtag::class,
            'workspaceLabel' => WorkspaceLabel::class,
        ]);
    }

    protected function configurePostHog(): void
    {
        $apiKey = config('services.posthog.api_key');

        if ($apiKey) {
            PostHog::init($apiKey, [
                'host' => config('services.posthog.host'),
            ]);
        }
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            if ($this->app->environment('local')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->workspace?->id ?: $request->ip());
        });
    }

    protected function configureStripeWebhooks(): void
    {
        Event::listen(WebhookReceived::class, StripeEventListener::class);
    }

    protected function configureSocialite(): void
    {
        // Google Auth (login/signup) - separate from YouTube OAuth
        Socialite::extend('google-auth', function ($app) {
            $config = $app['config']['services.google-auth'];

            return Socialite::buildProvider(GoogleProvider::class, $config);
        });

        // Instagram Business Login
        Socialite::extend('instagram', function ($app) {
            $config = $app['config']['services.instagram'];

            return Socialite::buildProvider(InstagramProvider::class, $config);
        });

        Event::listen(SocialiteWasCalled::class, FacebookExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, LinkedInExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, LinkedInPageExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, PinterestExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, TikTokExtendSocialite::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Disable wrapping of JSON resources
        JsonResource::withoutWrapping();
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );

        Nightwatch::rejectCacheEvents(function (CacheEvent $cacheEvent) {
            return in_array($cacheEvent->key, [
                'illuminate:foundation:down',
                'illuminate:queue:restart',
                'illuminate:schedule:interrupt',
            ]);
        });

        // Custom email verification template
        VerifyEmail::toMailUsing(function (User $user, string $url) {
            return (new MailMessage)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->subject('Verify your email address')
                ->view('mail.email-verification', [
                    'title' => 'Verify your email address',
                    'previewText' => 'Please verify your email address.',
                    'user' => $user,
                    'url' => $url,
                ]);
        });

        // Custom password reset template
        ResetPassword::toMailUsing(function (User $user, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->subject('Reset your password')
                ->view('mail.password-reset', [
                    'title' => 'Reset your password',
                    'previewText' => 'Reset your password.',
                    'user' => $user,
                    'url' => $url,
                ]);
        });
    }
}
