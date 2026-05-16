<?php

use App\Console\Commands\PurgeExpiredMediaCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/**
 * Console Routes — OmnyRestore
 *
 * This file registers scheduled Artisan commands.
 * The scheduler runs via a server cron job:
 *   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
 *
 * View scheduled commands: php artisan schedule:list
 * Test locally:            php artisan schedule:work
 */

// ─── GDPR: Photo Purge ────────────────────────────────────────────────────────
// Deletes all S3 media files for orders delivered more than 6 months ago.
// Runs at 02:00 AM daily (low-traffic window).
// GDPR Article 5(1)(e) — Storage Limitation compliance.
Schedule::command(PurgeExpiredMediaCommand::class)
         ->dailyAt('02:00')
         ->withoutOverlapping()   // Prevent concurrent runs on high-volume servers
         ->runInBackground()      // Non-blocking: doesn't delay other scheduled commands
         ->appendOutputTo(storage_path('logs/gdpr-purge.log')); // Log output for audit trail

// ─── Tickets: Auto-closure ───────────────────────────────────────────────────
// Closes tickets that have been waiting for client response for more than 24 hours.
// Runs hourly to ensure timely closure of inactive threads.
Schedule::command(\App\Console\Commands\CloseInactiveTicketsCommand::class)
         ->hourly()
         ->withoutOverlapping()
         ->runInBackground();

// ─── Dev utility ──────────────────────────────────────────────────────────────
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

