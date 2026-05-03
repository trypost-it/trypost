<?php

declare(strict_types=1);

namespace App\Actions\Signature;

use App\Models\Workspace;
use App\Models\WorkspaceSignature;

class CreateSignature
{
    public static function execute(Workspace $workspace, array $data): WorkspaceSignature
    {
        return $workspace->signatures()->create([
            'name' => data_get($data, 'name'),
            'content' => data_get($data, 'content'),
        ]);
    }
}
