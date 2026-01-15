<?php

use App\Jobs\ProcessScheduledPosts;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ProcessScheduledPosts)->everyMinute();
