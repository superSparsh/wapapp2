<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Process due campaigns periodically
Schedule::command('campaigns:process-due')->everyMinute();
