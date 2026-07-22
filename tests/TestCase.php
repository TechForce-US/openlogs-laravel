<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Orchestra\Testbench\TestCase as Orchestra;
use TechForce\OpenLogs\Laravel\OpenLogsChannel;
use TechForce\OpenLogs\Laravel\OpenLogsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [OpenLogsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('openlogs.url', 'https://logs.example.com');
        $config->set('openlogs.api_key', 'secret');
        $config->set('openlogs.fallback_channel', 'capture');

        // The OpenLogs channel under test.
        $config->set('logging.channels.openlogs', [
            'driver' => 'custom',
            'via'    => OpenLogsChannel::class,
        ]);

        // A capture channel backed by a shared TestHandler so we can inspect
        // records replayed to the fallback.
        $app->instance('capture.handler', new TestHandler());
        $config->set('logging.channels.capture', [
            'driver' => 'custom',
            'via'    => fn () => new Logger('capture', [$app->make('capture.handler')]),
        ]);

        $config->set('logging.channels.single', [
            'driver' => 'single',
            'path'   => $app->storagePath('logs/test.log'),
            'level'  => 'debug',
        ]);
    }

    protected function captureHandler(): TestHandler
    {
        return $this->app->make('capture.handler');
    }
}
