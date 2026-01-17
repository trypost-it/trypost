<?php

namespace App\Exceptions;

use Exception;

class TokenExpiredException extends Exception
{
    public function __construct(
        string $message = 'Access token has expired or been revoked',
        public ?string $platformErrorCode = null
    ) {
        parent::__construct($message);
    }
}
