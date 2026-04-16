<?php

declare(strict_types=1);

use App\Broadcasting\PostChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('post.{post}', PostChannel::class);
