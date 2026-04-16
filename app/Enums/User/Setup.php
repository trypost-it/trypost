<?php

declare(strict_types=1);

namespace App\Enums\User;

enum Setup: string
{
    case Registering = 'registering';
    case Role = 'role';
    case Brand = 'brand';
    case Connections = 'connections';
    case Subscription = 'subscription';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Registering => 'Registering',
            self::Role => 'Select Role',
            self::Brand => 'Configure Brand',
            self::Connections => 'Connect Accounts',
            self::Subscription => 'Start Subscription',
            self::Completed => 'Completed',
        };
    }

    public function stepNumber(): int
    {
        return match ($this) {
            self::Registering => 0,
            self::Role => 1,
            self::Brand => 2,
            self::Connections => 3,
            self::Subscription => 4,
            self::Completed => 5,
        };
    }
}
