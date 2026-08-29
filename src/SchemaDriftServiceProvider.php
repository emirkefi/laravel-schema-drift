<?php

namespace EmirKefi\SchemaDrift;

use Illuminate\Support\ServiceProvider;
use EmirKefi\SchemaDrift\Commands\DriftCheckCommand;
use EmirKefi\SchemaDrift\Commands\GenerateMigrationCommand;

class SchemaDriftServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/schema-drift.php', 'schema-drift');

        // Dynamically inject the in-memory SQLite shadow connection into Laravel's config
        if (!config()->has('database.connections.schema_drift_shadow')) {
            config()->set('database.connections.schema_drift_shadow', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/schema-drift.php' => config_path('schema-drift.php'),
            ], 'schema-drift-config');

            $this->commands([
                DriftCheckCommand::class,
                GenerateMigrationCommand::class,
            ]);
        }
    }
}