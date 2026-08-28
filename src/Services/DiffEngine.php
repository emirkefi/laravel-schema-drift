<?php

namespace EmirKefi\SchemaDrift\Services;

use EmirKefi\SchemaDrift\Data\SchemaDiff;

class DiffEngine
{
    /**
     * @return array<SchemaDiff>
     */
    public function compare(array $liveSchema, array $expectedSchema): array
    {
        $diffs = [];

        // 1. Check for Missing or Extra Tables
        foreach ($expectedSchema as $table => $expectedData) {
            if (!isset($liveSchema[$table])) {
                $diffs[] = new SchemaDiff($table, '-', 'Present', 'Missing', 'MISSING_TABLE');
                continue;
            }

            // 2. Check Columns
            $diffs = array_merge($diffs, $this->compareColumns($table, $liveSchema[$table]['columns'] ?? [], $expectedData['columns'] ?? []));

            // 3. Check Indexes
            if ($this->getConfig('schema-drift.check_indexes', true)) {
                $diffs = array_merge($diffs, $this->compareIndexes($table, $liveSchema[$table]['indexes'] ?? [], $expectedData['indexes'] ?? []));
            }

            // 4. Check Foreign Keys
            if ($this->getConfig('schema-drift.check_foreign_keys', true)) {
                $diffs = array_merge($diffs, $this->compareForeignKeys($table, $liveSchema[$table]['foreign_keys'] ?? [], $expectedData['foreign_keys'] ?? []));
            }
        }

        // 5. Check for Untracked Tables in Live DB
        foreach ($liveSchema as $table => $data) {
            if (!isset($expectedSchema[$table])) {
                $diffs[] = new SchemaDiff($table, '-', 'Missing in Migrations', 'Present in DB', 'UNTRACKED_TABLE');
            }
        }

        return $diffs;
    }

    protected function compareColumns(string $table, array $liveCols, array $expectedCols): array
    {
        $diffs = [];
        $checkTypes = $this->getConfig('schema-drift.check_types', true);
        $checkDefaults = $this->getConfig('schema-drift.check_defaults', true);

        foreach ($expectedCols as $col => $expected) {
            if (!isset($liveCols[$col])) {
                $diffs[] = new SchemaDiff($table, "col: {$col}", 'Present', 'Missing', 'MISSING_COLUMN');
                continue;
            }

            $live = $liveCols[$col];

            // Check Nullability
            if (($expected['nullable'] ?? false) !== ($live['nullable'] ?? false)) {
                $expStr = ($expected['nullable'] ?? false) ? 'nullable' : 'not null';
                $actStr = ($live['nullable'] ?? false) ? 'nullable' : 'not null';
                $diffs[] = new SchemaDiff($table, "col: {$col} (nullability)", $expStr, $actStr, 'NULLABILITY_MISMATCH');
            }

            // Check Column Type
            if ($checkTypes && isset($expected['type'], $live['type']) && $expected['type'] !== $live['type']) {
                $diffs[] = new SchemaDiff($table, "col: {$col} (type)", (string) $expected['type'], (string) $live['type'], 'TYPE_MISMATCH');
            }

            // Check Column Default
            if ($checkDefaults && array_key_exists('default', $expected) && array_key_exists('default', $live)) {
                if ($expected['default'] !== $live['default']) {
                    $expDef = $expected['default'] === null ? 'NULL' : (string) $expected['default'];
                    $actDef = $live['default'] === null ? 'NULL' : (string) $live['default'];
                    $diffs[] = new SchemaDiff($table, "col: {$col} (default)", $expDef, $actDef, 'DEFAULT_MISMATCH');
                }
            }
        }

        foreach ($liveCols as $col => $live) {
            if (!isset($expectedCols[$col])) {
                $diffs[] = new SchemaDiff($table, "col: {$col}", 'Missing', 'Present', 'UNTRACKED_COLUMN');
            }
        }

        return $diffs;
    }

    protected function compareIndexes(string $table, array $liveIndexes, array $expectedIndexes): array
    {
        $diffs = [];

        foreach ($expectedIndexes as $idx => $expected) {
            if (!isset($liveIndexes[$idx])) {
                $diffs[] = new SchemaDiff($table, "index: {$idx}", 'Present', 'Missing', 'MISSING_INDEX');
            }
        }

        return $diffs;
    }

    protected function compareForeignKeys(string $table, array $liveFks, array $expectedFks): array
    {
        $diffs = [];

        foreach ($expectedFks as $fk => $expected) {
            if (!isset($liveFks[$fk])) {
                $diffs[] = new SchemaDiff($table, "fk: {$fk}", 'Present', 'Missing', 'MISSING_FOREIGN_KEY');
            }
        }

        return $diffs;
    }

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            return config($key, $default);
        }

        return $default;
    }
}