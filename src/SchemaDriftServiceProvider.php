<?php

namespace EmirKefi\SchemaDrift;

use Illuminate\Support\ServiceProvider;
use EmirKefi\SchemaDrift\Commands\DriftCheckCommand;

class SchemaDriftServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/schema-drift.php', 'schema-drift');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/schema-drift.php' => config_path('schema-drift.php'),
            ], 'schema-drift-config');

            $this->commands([
                DriftCheckCommand::class,
            ]);
        }
    }
}