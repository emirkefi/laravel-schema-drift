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
        $conn = DB::connection($this->connection);
        $driver = $conn->getDriverName();
        $prefix = (string) $conn->getTablePrefix();
        $prefixIndexes = (bool) ($conn->getConfig('prefix_indexes') ?? false);
        $ignorePatterns = config('schema-drift.ignore_tables', []);
        
        $tables = $schema->getTables();
        $snapshot = [];

        foreach ($tables as $table) {
            $rawTableName = $table['name'] ?? $table;
            
            $hasPrefix = (!empty($prefix) && str_starts_with($rawTableName, $prefix));
            $logicalTableName = $hasPrefix ? substr($rawTableName, strlen($prefix)) : $rawTableName;

            // Check if either the physical or logical table name matches any ignore pattern (exact or wildcard)
            $shouldIgnore = false;
            foreach ($ignorePatterns as $pattern) {
                if (Str::is($pattern, $rawTableName) || Str::is($pattern, $logicalTableName)) {
                    $shouldIgnore = true;
                    break;
                }
            }

            if ($shouldIgnore) {
                continue;
            }

            if (!$hasPrefix && !empty($prefix)) {
                // Table doesn't carry connection prefix; clear prefix temporarily for introspecting this table
                $conn->setTablePrefix('');
                try {
                    $columns = $schema->getColumns($rawTableName);
                    $indexes = $schema->getIndexes($rawTableName);
                    $foreignKeys = $schema->getForeignKeys($rawTableName);
                } finally {
                    $conn->setTablePrefix($prefix);
                }
                $snapshot[$logicalTableName] = [
                    'columns' => $this->normalizeColumns($columns, $driver),
                    'indexes' => $this->normalizeIndexes($indexes, '', false),
                    'foreign_keys' => $this->normalizeForeignKeys($foreignKeys, ''),
                ];
            } else {
                $snapshot[$logicalTableName] = [
                    'columns' => $this->normalizeColumns($schema->getColumns($logicalTableName), $driver),
                    'indexes' => $this->normalizeIndexes($schema->getIndexes($logicalTableName), $prefix, $prefixIndexes),
                    'foreign_keys' => $this->normalizeForeignKeys($schema->getForeignKeys($logicalTableName), $prefix),
                ];
            }
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

    protected function normalizeIndexes(array $indexes, string $prefix = '', bool $prefixIndexes = false): array
    {
        $normalized = [];
        foreach ($indexes as $index) {
            $name = $index['name'];

            // Normalize index name if index prefixing was applied
            if ($prefixIndexes && !empty($prefix) && str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
            }

            $normalized[$name] = [
                'columns' => $index['columns'] ?? [],
                'unique' => (bool) ($index['unique'] ?? false),
                'primary' => (bool) ($index['primary'] ?? false),
            ];
        }
        return $normalized;
    }

    protected function normalizeForeignKeys(array $foreignKeys, string $prefix = ''): array
    {
        $normalized = [];
        foreach ($foreignKeys as $fk) {
            $name = $fk['name'] ?? '';
            $foreignTable = $fk['foreign_table'] ?? '';

            // Normalize foreign table name if physical prefix is attached
            if (!empty($prefix) && str_starts_with($foreignTable, $prefix)) {
                $foreignTable = substr($foreignTable, strlen($prefix));
            }

            // Normalize foreign key name if physical prefix is attached
            if (!empty($prefix) && !empty($name) && str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
            }

            $normalized[$name] = [
                'columns' => $fk['columns'] ?? [],
                'foreign_table' => $foreignTable,
                'foreign_columns' => $fk['foreign_columns'] ?? [],
            ];
        }
        return $normalized;
    }
}