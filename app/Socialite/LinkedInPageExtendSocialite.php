<?php

namespace App\Socialite;

use SocialiteProviders\LinkedIn\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class LinkedInPageExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('linkedin-openid', Provider::class);
    }
}
