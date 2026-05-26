<?php

use App\Console\Commands\AnalysisReportCommand;
use App\Console\Commands\CreateApiUserCommand;
use App\Console\Commands\EnqueueProcessingCommand;
use App\Console\Commands\ExportDigestsCommand;
use App\Console\Commands\FetchFeedsCommand;
use App\Console\Commands\RotateApiTokenCommand;
use App\Console\Commands\SelectionReportCommand;
use App\Console\Commands\SelectionRollbackCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        AnalysisReportCommand::class,
        CreateApiUserCommand::class,
        EnqueueProcessingCommand::class,
        ExportDigestsCommand::class,
        FetchFeedsCommand::class,
        RotateApiTokenCommand::class,
        SelectionReportCommand::class,
        SelectionRollbackCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();
