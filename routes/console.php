<?php

use App\Console\Commands\CheckSocialConnections;
use App\Console\Commands\ProcessScheduledPosts;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessScheduledPosts::class)->everyMinute();
Schedule::command(CheckSocialConnections::class)->daily();
