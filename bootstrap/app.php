<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withCommands([
        AboutCommand::class,
        \App\Console\Commands\UpdateInventoryMetrics::class,
        \App\Console\Commands\PreloadPegawaiData::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('inventory:update-monthly-metrics')
        ->dailyAt('23:59')
        ->when(function () {
            return now()->endOfMonth()->isToday();
        });
        // ->timezone('Asia/Jakarta');

        $schedule->command('inventory:update-yearly-metrics')
        ->yearlyOn(12, 31, '23:59');
    })
    ->create();
