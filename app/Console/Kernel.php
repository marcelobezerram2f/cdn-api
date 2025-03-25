<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
         $schedule->command('summary:request')->dailyAt('2:00');
         $schedule->command('cname:check')->everyMinute();
         $schedule->command('cdn:queue')->everyMinute();
         $schedule->command('ssl:install')->everyMinute();
         $schedule->command('cdn-resource:delete')->everyMinute();
         $schedule->command('dipatcher:worker')->everyMinute();
         $schedule->command('tries-ssl-install:reset')->everyMinute();
         $schedule->command('ssl:renew')->dailyAt('1:00');
    }
    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
