<?php

namespace EmirKefi\SchemaDrift\Extractors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use EmirKefi\SchemaDrift\Services\TypeNormalizer;

class SchemaExtractor
{
    protected TypeNormalizer $typeNormalizer;

    public function __construct(
        protected string $connection = 'default',
        ?TypeNormalizer $typeNormalizer = null
    ) {
        $this->typeNormalizer = $typeNormalizer ?? new TypeNormalizer();
    }

    public function extract(): array
    {
        $schema = Schema::connection($this->connection);
        $driver = DB::connection($this->connection)->getDriverName();
        $ignorePatterns = config('schema-drift.ignore_tables', []);
        
        $tables = $schema->getTables();
        $snapshot = [];

        foreach ($tables as $table) {
            $tableName = $table['name'] ?? $table;
            
            // Check if the table matches any ignore pattern (exact or wildcard)
            $shouldIgnore = false;
            foreach ($ignorePatterns as $pattern) {
                if (Str::is($pattern, $tableName)) {
                    $shouldIgnore = true;
                    break;
                }
            }

            if ($shouldIgnore) {
                continue;
            }

            $snapshot[$tableName] = [
                'columns' => $this->normalizeColumns($schema->getColumns($tableName), $driver),
                'indexes' => $this->normalizeIndexes($schema->getIndexes($tableName)),
                'foreign_keys' => $this->normalizeForeignKeys($schema->getForeignKeys($tableName)),
            ];
        }

        return $snapshot;
    }

    protected function normalizeColumns(array $columns, string $driver): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            $normalized[$column['name']] = $this->typeNormalizer->normalizeColumn($column, $driver);
        }
        return $normalized;
    }

    protected function normalizeIndexes(array $indexes): array
    {
        $normalized = [];
        foreach ($indexes as $index) {
            $normalized[$index['name']] = [
                'columns' => $index['columns'] ?? [],
                'unique' => (bool) ($index['unique'] ?? false),
                'primary' => (bool) ($index['primary'] ?? false),
            ];
        }
        return $normalized;
    }

    protected function normalizeForeignKeys(array $foreignKeys): array
    {
        $normalized = [];
        foreach ($foreignKeys as $fk) {
            $normalized[$fk['name']] = [
                'columns' => $fk['columns'] ?? [],
                'foreign_table' => $fk['foreign_table'] ?? '',
                'foreign_columns' => $fk['foreign_columns'] ?? [],
            ];
        }
        return $normalized;
    }
}