<?php

declare(strict_types=1);

namespace App\Enums\User;

enum Persona: string
{
    case Founder = 'founder';
    case Creator = 'creator';
    case Agency = 'agency';
    case Enterprise = 'enterprise';
    case SmallBusiness = 'small_business';
    case Personal = 'personal';

    public function label(): string
    {
        return __("onboarding.personas.{$this->value}.label");
    }

    public function description(): string
    {
        return __("onboarding.personas.{$this->value}.description");
    }

    public function icon(): string
    {
        return match ($this) {
            self::Founder => 'rocket',
            self::Creator => 'sparkles',
            self::Agency => 'building',
            self::Enterprise => 'building-2',
            self::SmallBusiness => 'store',
            self::Personal => 'user',
        };
    }

    /**
     * @return array<array{value: string, label: string, description: string, icon: string}>
     */
    public static function toSelectArray(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
                'icon' => $case->icon(),
            ],
            self::cases()
        );
    }
}
