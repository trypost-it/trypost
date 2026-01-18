<?php

namespace App\Providers;

use App\Listeners\StripeEventListener;
use App\Models\Language;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHashtag;
use App\Models\WorkspaceInvite;
use App\Models\WorkspaceLabel;
use App\Socialite\InstagramProvider;
use App\Socialite\LinkedInPageExtendSocialite;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Socialite\Facades\Socialite;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureSocialite();
        $this->configureStripeWebhooks();

        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);
    }

    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'language' => Language::class,
            'media' => Media::class,
            'post' => Post::class,
            'postPlatform' => PostPlatform::class,
            'socialAccount' => SocialAccount::class,
            'subscription' => Subscription::class,
            'subscriptionItem' => SubscriptionItem::class,
            'user' => User::class,
            'workspace' => Workspace::class,
            'workspaceHashtag' => WorkspaceHashtag::class,
            'workspaceInvite' => WorkspaceInvite::class,
            'workspaceLabel' => WorkspaceLabel::class,
        ]);
    }

    protected function configureStripeWebhooks(): void
    {
        Event::listen(WebhookReceived::class, StripeEventListener::class);
    }

    protected function configureSocialite(): void
    {
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
                ->subject('Confirme o seu endereço de e-mail')
                ->view('mail.email-verification', [
                    'title' => 'Confirme o seu endereço de e-mail',
                    'previewText' => 'Por favor, confirme o seu endereço de e-mail.',
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
                ->subject('Redefina sua senha')
                ->view('mail.password-reset', [
                    'title' => 'Redefina sua senha',
                    'previewText' => 'Por favor, redefina sua senha.',
                    'user' => $user,
                    'url' => $url,
                ]);
        });
    }
}
