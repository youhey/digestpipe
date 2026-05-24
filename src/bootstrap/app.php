<?php

use App\Console\Commands\EnqueueProcessingCommand;
use App\Console\Commands\ExportDigestsCommand;
use App\Console\Commands\FetchFeedsCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        EnqueueProcessingCommand::class,
        ExportDigestsCommand::class,
        FetchFeedsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();
