<?php

namespace YourVendor\SchemaDrift\Extractors;

use Illuminate\Support\Facades\Schema;

class SchemaExtractor
{
    public function __construct(
        protected string $connection = 'default'
    ) {}

    public function extract(): array
    {
        $schema = Schema::connection($this->connection);
        $ignoreTables = config('schema-drift.ignore_tables', []);
        
        $tables = $schema->getTables();
        $snapshot = [];

        foreach ($tables as $table) {
            $tableName = $table['name'] ?? $table;
            
            if (in_array($tableName, $ignoreTables, true)) {
                continue;
            }

            $snapshot[$tableName] = [
                'columns' => $this->normalizeColumns($schema->getColumns($tableName)),
                'indexes' => $this->normalizeIndexes($schema->getIndexes($tableName)),
                'foreign_keys' => $this->normalizeForeignKeys($schema->getForeignKeys($tableName)),
            ];
        }

        return $snapshot;
    }

    protected function normalizeColumns(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            $normalized[$column['name']] = [
                'type' => strtolower($column['type_name'] ?? $column['type'] ?? 'unknown'),
                'nullable' => (bool) ($column['nullable'] ?? false),
                'default' => $column['default'] ?? null,
            ];
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