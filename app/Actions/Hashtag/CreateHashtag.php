<?php

declare(strict_types=1);

namespace App\Actions\Hashtag;

use App\Models\Workspace;
use App\Models\WorkspaceHashtag;

class CreateHashtag
{
    public static function execute(Workspace $workspace, array $data): WorkspaceHashtag
    {
        return $workspace->hashtags()->create([
            'name' => data_get($data, 'name'),
            'hashtags' => data_get($data, 'hashtags'),
        ]);
    }
}
