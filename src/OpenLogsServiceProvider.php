<?php

declare(strict_types=1);

namespace TechForce\OpenLogs\Laravel;

use Illuminate\Support\ServiceProvider;

final class OpenLogsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/openlogs.php', 'openlogs');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/openlogs.php' => $this->app->configPath('openlogs.php'),
            ], 'openlogs-config');
        }
    }
}
