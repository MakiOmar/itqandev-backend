<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Automatic database backups
|--------------------------------------------------------------------------
|
| Driven by config/database-backup.php (DB_BACKUP_INTERVAL / DB_BACKUP_AT).
| Host must invoke `php artisan schedule:run` every minute.
|
*/
$backupInterval = strtolower(trim((string) config('database-backup.schedule_interval', 'disabled')));
$backupAt = (string) config('database-backup.schedule_at', '02:00');
if (! preg_match('/^\d{1,2}:\d{2}$/', $backupAt)) {
    $backupAt = '02:00';
}
$backupWeeklyDay = (int) config('database-backup.schedule_weekly_day', 1);

$backupEvent = match ($backupInterval) {
    'hourly' => Schedule::command('backup:database --force')->hourly(),
    'every_six_hours', 'every6hours', '6h' => Schedule::command('backup:database --force')->everySixHours(),
    'daily' => Schedule::command('backup:database --force')->dailyAt($backupAt),
    'weekly' => Schedule::command('backup:database --force')->weeklyOn($backupWeeklyDay, $backupAt),
    default => null,
};

if ($backupEvent !== null) {
    $backupEvent
        ->name('database-backup')
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/database-backup-schedule.log'));
}
