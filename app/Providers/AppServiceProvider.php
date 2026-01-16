<?php

namespace App\Providers;

use App\Socialite\InstagramExtendSocialite;
use App\Socialite\LinkedInPageExtendSocialite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\LinkedIn\LinkedInExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
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
        $this->configureSocialite();
    }

    protected function configureSocialite(): void
    {
        Event::listen(SocialiteWasCalled::class, InstagramExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, LinkedInExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, LinkedInPageExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, TikTokExtendSocialite::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
    }
}
