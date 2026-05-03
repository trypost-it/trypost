<?php

declare(strict_types=1);

namespace App\Actions\Signature;

use App\Models\WorkspaceSignature;

class UpdateSignature
{
    public static function execute(WorkspaceSignature $signature, array $data): WorkspaceSignature
    {
        $signature->update([
            'name' => data_get($data, 'name'),
            'content' => data_get($data, 'content'),
        ]);

        return $signature;
    }
}
