<?php

namespace App\Enums\User;

enum Setup: string
{
    case Registering = 'registering';
    case Role = 'role';
    case Connections = 'connections';
    case Subscription = 'subscription';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Registering => 'Registering',
            self::Role => 'Select Role',
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
            self::Connections => 2,
            self::Subscription => 3,
            self::Completed => 4,
        };
    }
}
