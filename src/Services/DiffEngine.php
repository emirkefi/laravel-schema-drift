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
            $diffs = array_merge($diffs, $this->compareColumns($table, $liveSchema[$table]['columns'], $expectedData['columns']));

            // 3. Check Indexes
            if (config('schema-drift.check_indexes', true)) {
                $diffs = array_merge($diffs, $this->compareIndexes($table, $liveSchema[$table]['indexes'], $expectedData['indexes']));
            }
        }

        // 4. Check for Untracked Tables in Live DB
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

        foreach ($expectedCols as $col => $expected) {
            if (!isset($liveCols[$col])) {
                $diffs[] = new SchemaDiff($table, "col: {$col}", 'Present', 'Missing', 'MISSING_COLUMN');
                continue;
            }

            $live = $liveCols[$col];

            if ($expected['nullable'] !== $live['nullable']) {
                $expStr = $expected['nullable'] ? 'nullable' : 'not null';
                $actStr = $live['nullable'] ? 'nullable' : 'not null';
                $diffs[] = new SchemaDiff($table, "col: {$col} (nullability)", $expStr, $actStr, 'NULLABILITY_MISMATCH');
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
}