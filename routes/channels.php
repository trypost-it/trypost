<?php

declare(strict_types=1);

use App\Broadcasting\PostChannel;
use App\Broadcasting\UserAiCreationChannel;
use App\Broadcasting\UserAiGenerationChannel;
use App\Broadcasting\WorkspaceUserChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('post.{post}', PostChannel::class);

Broadcast::channel('workspace.{workspace}.user.{owner}', WorkspaceUserChannel::class);

Broadcast::channel('users.{userId}.ai-gen.{generationId}', UserAiGenerationChannel::class);

Broadcast::channel('users.{userId}.ai-creation.{creationId}', UserAiCreationChannel::class);
