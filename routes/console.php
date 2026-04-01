<?php

declare(strict_types=1);

use App\Console\Commands\CheckSocialConnections;
use App\Console\Commands\ProcessScheduledPosts;
use App\Console\Commands\RefreshExpiringTokens;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessScheduledPosts::class)->everyMinute();
Schedule::command(CheckSocialConnections::class)->daily();
Schedule::command(RefreshExpiringTokens::class)->hourly();
