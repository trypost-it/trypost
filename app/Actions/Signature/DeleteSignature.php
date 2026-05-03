<?php

declare(strict_types=1);

namespace App\Actions\Signature;

use App\Models\WorkspaceSignature;

class DeleteSignature
{
    public static function execute(WorkspaceSignature $signature): void
    {
        $signature->delete();
    }
}
