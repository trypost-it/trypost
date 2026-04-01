<?php

declare(strict_types=1);

namespace App\Actions\Hashtag;

use App\Models\WorkspaceHashtag;

class DeleteHashtag
{
    public static function execute(WorkspaceHashtag $hashtag): void
    {
        $hashtag->delete();
    }
}
