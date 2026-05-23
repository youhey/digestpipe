<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);
    }

    public function createApplication(): Application
    {
        $app = require Application::inferBasePath() . '/bootstrap/app.php';

        assert($app instanceof Application);

        $app->loadEnvironmentFrom('.env.example');

        $kernel = $app->make(Kernel::class);

        $kernel->bootstrap();

        return $app;
    }
}
