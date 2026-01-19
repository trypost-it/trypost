<?php

use App\Console\Commands\ProcessScheduledPosts;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ProcessScheduledPosts::class)->everyMinute();
