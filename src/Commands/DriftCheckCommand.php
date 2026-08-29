<?php

namespace EmirKefi\SchemaDrift\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use EmirKefi\SchemaDrift\Extractors\SchemaExtractor;
use EmirKefi\SchemaDrift\Services\DiffEngine;
use EmirKefi\SchemaDrift\Services\MigrationGenerator;
use EmirKefi\SchemaDrift\Services\OutputFormatter;

class DriftCheckCommand extends Command
{
    protected $signature = 'schema:drift 
                            {--connection= : The database connection to check against migrations}
                            {--shadow-connection= : Custom shadow database connection for running migrations}
                            {--path=database/migrations : Path to migration files}
                            {--fresh-shadow : Run migrate:fresh on the shadow connection before extracting schema}
                            {--format=table : Output format: table, json, github, markdown}
                            {--min-severity=warning : Minimum severity level to trigger failure (warning, error)}
                            {--fail-on-drift : Return a non-zero exit code if schema drift is detected}
                            {--fix : Automatically generate a migration to fix detected schema drift}
                            {--destructive : Include destructive operations (e.g. drop missing columns/tables) in the fix migration}';

    protected $description = 'Detect schema drift between live database tables and migration files';

    public function handle(
        DiffEngine $diffEngine,
        MigrationGenerator $migrationGenerator,
        OutputFormatter $outputFormatter
    ): int {
        $targetConnection = $this->option('connection') ?? config('database.default');
        $shadowConnection = $this->option('shadow-connection') ?? config('schema-drift.shadow_connection');
        $migrationPath = $this->option('path');
        $format = strtolower($this->option('format') ?? config('schema-drift.default_format', 'table'));
        $minSeverity = strtolower($this->option('min-severity') ?? config('schema-drift.min_severity', 'warning'));
        $isTableFormat = ($format === 'table');

        if ($shadowConnection !== null && $shadowConnection === $targetConnection) {
            $this->error("⚠️  Safety Error: Target connection [{$targetConnection}] and shadow connection [{$shadowConnection}] cannot be the same.");
            return self::FAILURE;
        }

        if ($isTableFormat) {
            $this->info(" Inspecting live schema on [{$targetConnection}]...");
        }
        
        $liveExtractor = new SchemaExtractor($targetConnection);
        $liveSchema = $liveExtractor->extract();

        if ($isTableFormat) {
            if ($shadowConnection) {
                $this->info(" Running migrations on custom shadow database [{$shadowConnection}]...");
            } else {
                $this->info(" Simulating migrations in temporary in-memory SQLite shadow database...");
            }
        }

        $expectedSchema = $this->extractExpectedSchema($migrationPath, $shadowConnection);

        if ($isTableFormat) {
            $this->info(" Comparing schema structures...");
        }

        $diffs = $diffEngine->compare($liveSchema, $expectedSchema);

        // Render Outputs based on requested format
        match ($format) {
            'json' => $this->line($outputFormatter->formatJson($diffs)),
            'github' => $this->line($outputFormatter->formatGithub($diffs)),
            'markdown' => $this->line($outputFormatter->formatMarkdown($diffs)),
            default => $this->renderTableOutput($diffs, $migrationGenerator, $liveSchema, $expectedSchema, $migrationPath),
        };

        // Determine Exit Code based on severity threshold
        if (empty($diffs)) {
            return self::SUCCESS;
        }

        $hasFailingIssues = match ($minSeverity) {
            'error' => count(array_filter($diffs, fn($d) => $d->severity === 'error')) > 0,
            default => true,
        };

        return $hasFailingIssues ? self::FAILURE : self::SUCCESS;
    }

    protected function renderTableOutput(
        array $diffs,
        MigrationGenerator $migrationGenerator,
        array $liveSchema,
        array $expectedSchema,
        string $migrationPath
    ): void {
        if (empty($diffs)) {
            $this->newLine();
            $this->info('✅ Schema is in perfect sync with your migrations! No drift detected.');
            return;
        }

        $this->newLine();
        $this->error('⚠️  Schema drift detected:');

        $formatter = new OutputFormatter();
        $rows = $formatter->formatTableRows($diffs);

        $this->table(
            ['Severity', 'Table', 'Attribute', 'Expected (Migrations)', 'Actual (Database)', 'Issue Type'],
            $rows
        );

        if ($this->option('fix')) {
            $this->newLine();
            $this->info('🛠️  Generating fix migration...');
            $destructive = (bool) $this->option('destructive');
            $content = $migrationGenerator->generate($diffs, $liveSchema, $expectedSchema, $destructive);
            $filePath = $migrationGenerator->write($content, $migrationPath);

            $this->info("✨ Fix migration generated successfully:");
            $this->line("   <fg=cyan>{$filePath}</>");
        } else {
            $this->newLine();
            $this->comment('💡 Tip: Run with --fix or use `php artisan schema:drift:generate-migration` to automatically generate a migration fixing this drift.');
        }
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