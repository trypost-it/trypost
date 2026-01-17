<?php

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
        return match ($this) {
            self::Founder => 'Founder',
            self::Creator => 'Creator',
            self::Agency => 'Agency',
            self::Enterprise => 'Enterprise',
            self::SmallBusiness => 'Small Business',
            self::Personal => 'Personal',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Founder => 'Building a startup or new venture',
            self::Creator => 'Content creator or influencer',
            self::Agency => 'Marketing or social media agency',
            self::Enterprise => 'Large company or corporation',
            self::SmallBusiness => 'Small to medium business',
            self::Personal => 'Personal brand or hobby',
        };
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
