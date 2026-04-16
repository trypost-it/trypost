<?php

declare(strict_types=1);

namespace App\Ai\PlatformRules;

use App\Enums\SocialAccount\Platform;

class Registry
{
    /**
     * @var array<string, class-string<Contract>>
     */
    private static array $map = [];

    public static function for(Platform $platform): ?Contract
    {
        $class = self::$map[$platform->value] ?? null;

        return $class ? new $class : null;
    }

    /**
     * @param  array<int, Platform>  $platforms
     * @return array<int, Contract>
     */
    public static function forMany(array $platforms): array
    {
        $rules = [];

        foreach ($platforms as $platform) {
            $rule = self::for($platform);
            if ($rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param  class-string<Contract>  $ruleClass
     */
    public static function register(Platform $platform, string $ruleClass): void
    {
        self::$map[$platform->value] = $ruleClass;
    }

    public static function clear(): void
    {
        self::$map = [];
    }
}
