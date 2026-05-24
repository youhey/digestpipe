<?php

use App\Console\Commands\CreateApiUserCommand;
use App\Console\Commands\EnqueueProcessingCommand;
use App\Console\Commands\ExportDigestsCommand;
use App\Console\Commands\FetchFeedsCommand;
use App\Console\Commands\RotateApiTokenCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        CreateApiUserCommand::class,
        EnqueueProcessingCommand::class,
        ExportDigestsCommand::class,
        FetchFeedsCommand::class,
        RotateApiTokenCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();
