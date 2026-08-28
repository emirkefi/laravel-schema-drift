<?php

namespace EmirKefi\SchemaDrift\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use EmirKefi\SchemaDrift\Extractors\SchemaExtractor;
use EmirKefi\SchemaDrift\Services\DiffEngine;

class DriftCheckCommand extends Command
{
    protected $signature = 'schema:drift 
                            {--connection= : The database connection to check against migrations}
                            {--shadow-connection= : Custom shadow database connection for running migrations}
                            {--path=database/migrations : Path to migration files}
                            {--fresh-shadow : Run migrate:fresh on the shadow connection before extracting schema}';

    protected $description = 'Detect schema drift between live database tables and migration files';

    public function handle(DiffEngine $diffEngine): int
    {
        $targetConnection = $this->option('connection') ?? config('database.default');
        $shadowConnection = $this->option('shadow-connection') ?? config('schema-drift.shadow_connection');
        $migrationPath = $this->option('path');

        if ($shadowConnection !== null && $shadowConnection === $targetConnection) {
            $this->error("⚠️  Safety Error: Target connection [{$targetConnection}] and shadow connection [{$shadowConnection}] cannot be the same.");
            return self::FAILURE;
        }

        $this->info(" Inspecting live schema on [{$targetConnection}]...");
        
        $liveExtractor = new SchemaExtractor($targetConnection);
        $liveSchema = $liveExtractor->extract();

        if ($shadowConnection) {
            $this->info(" Running migrations on custom shadow database [{$shadowConnection}]...");
        } else {
            $this->info(" Simulating migrations in temporary in-memory SQLite shadow database...");
        }

        $expectedSchema = $this->extractExpectedSchema($migrationPath, $shadowConnection);

        $this->info(" Comparing schema structures...");
        $diffs = $diffEngine->compare($liveSchema, $expectedSchema);

        if (empty($diffs)) {
            $this->newLine();
            $this->info('✅ Schema is in perfect sync with your migrations! No drift detected.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('⚠️  Schema drift detected:');
        
        $rows = array_map(fn($diff) => $diff->toArray(), $diffs);

        $this->table(
            ['Table', 'Attribute', 'Expected (Migrations)', 'Actual (Database)', 'Issue Type'],
            $rows
        );

        return self::FAILURE;
    }

    protected function extractExpectedSchema(string $migrationPath, ?string $customShadowConnection = null): array
    {
        $isCustomShadow = !empty($customShadowConnection);
        $shadowConnection = $isCustomShadow ? $customShadowConnection : 'schema_drift_shadow';

        if (!$isCustomShadow) {
            // Setup temporary in-memory SQLite shadow connection
            Config::set("database.connections.{$shadowConnection}", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }

        $shouldFresh = $this->option('fresh-shadow') || config('schema-drift.fresh_shadow', false);
        $migrateCommand = ($isCustomShadow && $shouldFresh) ? 'migrate:fresh' : 'migrate';

        // Run migrations on the shadow database
        Artisan::call($migrateCommand, [
            '--database' => $shadowConnection,
            '--path' => $migrationPath,
            '--force' => true,
        ]);

        $shadowExtractor = new SchemaExtractor($shadowConnection);
        $snapshot = $shadowExtractor->extract();

        if (!$isCustomShadow) {
            DB::purge($shadowConnection);
        }

        return $snapshot;
    }
}