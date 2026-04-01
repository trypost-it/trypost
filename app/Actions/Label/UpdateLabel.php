<?php

declare(strict_types=1);

namespace App\Actions\Label;

use App\Models\WorkspaceLabel;

class UpdateLabel
{
    public static function execute(WorkspaceLabel $label, array $data): WorkspaceLabel
    {
        $label->update([
            'name' => data_get($data, 'name'),
            'color' => data_get($data, 'color'),
        ]);

        return $label;
    }
}
