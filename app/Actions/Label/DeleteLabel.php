<?php

declare(strict_types=1);

namespace App\Actions\Label;

use App\Models\WorkspaceLabel;

class DeleteLabel
{
    public static function execute(WorkspaceLabel $label): void
    {
        $label->delete();
    }
}
