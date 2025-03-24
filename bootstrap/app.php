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
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('inventory:update-metrics')
            ->dailyAt('00:00')
            // ->yearlyOn(1, 1, '00:00')
            ->timezone('Asia/Jakarta');
    })
    ->create();
