<?php

namespace EmirKefi\SchemaDrift\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use EmirKefi\SchemaDrift\Extractors\SchemaExtractor;
use EmirKefi\SchemaDrift\Services\DiffEngine;
use EmirKefi\SchemaDrift\Services\MigrationGenerator;

class GenerateMigrationCommand extends Command
{
    protected $signature = 'schema:drift:generate-migration 
                            {--connection= : The database connection to check against migrations}
                            {--shadow-connection= : Custom shadow database connection for running migrations}
                            {--path=database/migrations : Path to existing migration files}
                            {--output= : Directory to save the generated migration file}
                            {--name=fix_schema_drift : Name of the generated migration}
                            {--destructive : Include destructive drop operations in the migration}
                            {--fresh-shadow : Run migrate:fresh on the shadow connection}';

    protected $description = 'Generate a timestamped Laravel migration to fix detected schema drift';

    public function handle(DiffEngine $diffEngine, MigrationGenerator $migrationGenerator): int
    {
        $targetConnection = $this->option('connection') ?? config('database.default');
        $shadowConnection = $this->option('shadow-connection') ?? config('schema-drift.shadow_connection');
        $migrationPath = $this->option('path');
        $outputPath = $this->option('output') ?? (function_exists('database_path') ? database_path('migrations') : getcwd() . '/database/migrations');
        $destructive = (bool) $this->option('destructive');
        $migrationName = (string) $this->option('name');

        if ($shadowConnection !== null && $shadowConnection === $targetConnection) {
            $this->error("⚠️  Safety Error: Target connection [{$targetConnection}] and shadow connection [{$shadowConnection}] cannot be the same.");
            return self::FAILURE;
        }

        $this->info(" Inspecting live schema on [{$targetConnection}]...");
        $liveExtractor = new SchemaExtractor($targetConnection);
        $liveSchema = $liveExtractor->extract();

        if ($shadowConnection) {
            $this->info(" Running migrations on shadow database [{$shadowConnection}]...");
        } else {
            $this->info(" Simulating migrations in temporary in-memory SQLite shadow database...");
        }

        $expectedSchema = $this->extractExpectedSchema($migrationPath, $shadowConnection);

        $this->info(" Comparing schema structures...");
        $diffs = $diffEngine->compare($liveSchema, $expectedSchema);

        if (empty($diffs)) {
            $this->newLine();
            $this->info('✅ Schema is in perfect sync! No migration needed.');
            return self::SUCCESS;
        }

        $this->info(' Generating fix migration...');
        $content = $migrationGenerator->generate($diffs, $liveSchema, $expectedSchema, $destructive);
        $filePath = $migrationGenerator->write($content, $outputPath, $migrationName);

        $this->newLine();
        $this->info("✨ Migration generated successfully:");
        $this->line("   <fg=cyan>{$filePath}</>");

        return self::SUCCESS;
    }

    protected function extractExpectedSchema(string $migrationPath, ?string $customShadowConnection = null): array
    {
        $isCustomShadow = !empty($customShadowConnection);
        $shadowConnection = $isCustomShadow ? $customShadowConnection : 'schema_drift_shadow';

        if (!$isCustomShadow) {
            Config::set("database.connections.{$shadowConnection}", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }

        $shouldFresh = $this->option('fresh-shadow') || config('schema-drift.fresh_shadow', false);
        $migrateCommand = ($isCustomShadow && $shouldFresh) ? 'migrate:fresh' : 'migrate';

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
