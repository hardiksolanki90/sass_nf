<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\RfGenViewCron::class,
        Commands\ReturnTruckAllocationCron::class,
        Commands\VehicleUtilisationReportCron::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('rfgen:cron')
            ->everyTwoMinutes();

        $schedule->command('return:cron')
            ->dailyAt('05:30');

        $schedule->command('vehicleutilisation:cron')
            ->dailyAt('01:30');

        $schedule->command('salesvsgrv:cron')
            ->dailyAt('02:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
