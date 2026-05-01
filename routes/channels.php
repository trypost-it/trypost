<?php

declare(strict_types=1);

use App\Broadcasting\PostChannel;
use App\Broadcasting\WorkspaceUserChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('post.{post}', PostChannel::class);

Broadcast::channel('workspace.{workspace}.user.{owner}', WorkspaceUserChannel::class);
