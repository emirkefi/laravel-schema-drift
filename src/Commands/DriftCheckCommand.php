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
                            {--path=database/migrations : Path to migration files}';

    protected $description = 'Detect schema drift between live database tables and migration files';

    public function handle(DiffEngine $diffEngine): int
    {
        $targetConnection = $this->option('connection') ?? config('database.default');
        $migrationPath = $this->option('path');

        $this->info("🔍 Inspecting live schema on [{$targetConnection}]...");
        
        $liveExtractor = new SchemaExtractor($targetConnection);
        $liveSchema = $liveExtractor->extract();

        $this->info("⚡ Simulating migrations in shadow database...");
        $expectedSchema = $this->extractExpectedSchema($migrationPath);

        $this->info("⚖️  Comparing schema structures...");
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

    protected function extractExpectedSchema(string $migrationPath): array
    {
        $shadowConnection = 'schema_drift_shadow';

        // Setup temporary in-memory SQLite shadow connection
        Config::set("database.connections.{$shadowConnection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Run migrations on the shadow database
        Artisan::call('migrate', [
            '--database' => $shadowConnection,
            '--path' => $migrationPath,
            '--force' => true,
        ]);

        $shadowExtractor = new SchemaExtractor($shadowConnection);
        $snapshot = $shadowExtractor->extract();

        DB::purge($shadowConnection);

        return $snapshot;
    }
}