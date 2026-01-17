<?php

namespace App\Enums\SocialAccount;

enum Status: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case TokenExpired = 'token_expired';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Disconnected => 'Disconnected',
            self::TokenExpired => 'Token Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Connected => 'green',
            self::Disconnected => 'red',
            self::TokenExpired => 'red',
        };
    }
}
