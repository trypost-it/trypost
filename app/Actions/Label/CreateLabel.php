<?php

declare(strict_types=1);

namespace App\Actions\Label;

use App\Models\Workspace;
use App\Models\WorkspaceLabel;

class CreateLabel
{
    public static function execute(Workspace $workspace, array $data): WorkspaceLabel
    {
        return $workspace->labels()->create([
            'name' => data_get($data, 'name'),
            'color' => data_get($data, 'color'),
        ]);
    }
}
