<?php

namespace App\Socialite;

use SocialiteProviders\Manager\SocialiteWasCalled;

class InstagramExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('instagram', \Laravel\Socialite\Two\FacebookProvider::class);
    }
}
