<?php

declare(strict_types=1);

namespace App\Actions\Hashtag;

use App\Models\WorkspaceHashtag;

class UpdateHashtag
{
    public static function execute(WorkspaceHashtag $hashtag, array $data): WorkspaceHashtag
    {
        $hashtag->update([
            'name' => data_get($data, 'name'),
            'hashtags' => data_get($data, 'hashtags'),
        ]);

        return $hashtag;
    }
}
