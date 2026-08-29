<?php

namespace EmirKefi\SchemaDrift\Services;

use EmirKefi\SchemaDrift\Data\SchemaDiff;

class OutputFormatter
{
    /**
     * Format diffs as JSON.
     *
     * @param array<SchemaDiff> $diffs
     */
    public function formatJson(array $diffs): string
    {
        $errorsCount = count(array_filter($diffs, fn($d) => $d->severity === 'error'));
        $warningsCount = count(array_filter($diffs, fn($d) => $d->severity === 'warning'));

        $data = [
            'in_sync' => empty($diffs),
            'total_issues' => count($diffs),
            'errors_count' => $errorsCount,
            'warnings_count' => $warningsCount,
            'issues' => array_map(fn($d) => $d->toArray(), $diffs),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Format diffs as GitHub Actions workflow annotations.
     *
     * @param array<SchemaDiff> $diffs
     */
    public function formatGithub(array $diffs): string
    {
        if (empty($diffs)) {
            return "::notice title=Schema Drift::Schema is in perfect sync with migrations.";
        }

        $lines = [];
        foreach ($diffs as $diff) {
            $command = $diff->severity === 'error' ? 'error' : 'warning';
            $title = "Schema Drift [{$diff->issueType}]";
            $message = "Table '{$diff->table}' {$diff->attribute} -> Expected: {$diff->expected}, Actual: {$diff->actual}";
            $lines[] = "::{$command} title={$title}::{$message}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format diffs as a Markdown table (e.g. for PR comments or GITHUB_STEP_SUMMARY).
     *
     * @param array<SchemaDiff> $diffs
     */
    public function formatMarkdown(array $diffs): string
    {
        if (empty($diffs)) {
            return "### Schema Drift Detection\n\nNo schema drift detected. Database is in perfect sync with your migrations!";
        }

        $lines = [
            "### Schema Drift Detected",
            "",
            "| Status | Table | Attribute | Expected (Migrations) | Actual (Database) | Issue Type |",
            "| :--- | :--- | :--- | :--- | :--- | :--- |",
        ];

        foreach ($diffs as $diff) {
            $status = match ($diff->category) {
                'MISSING' => '[MISSING]',
                'MISMATCH' => '[MISMATCH]',
                default => '[UNTRACKED]',
            };
            $lines[] = "| `{$status}` | `{$diff->table}` | `{$diff->attribute}` | `{$diff->expected}` | `{$diff->actual}` | `{$diff->issueType}` |";
        }

        return implode("\n", $lines);
    }

    /**
     * Format diff rows for terminal table output with color coding.
     *
     * @param array<SchemaDiff> $diffs
     * @return array<array>
     */
    public function formatTableRows(array $diffs): array
    {
        return array_map(function (SchemaDiff $diff) {
            $statusBadge = match ($diff->category) {
                'MISSING' => '<fg=red>MISSING</>',
                'MISMATCH' => '<fg=yellow>MISMATCH</>',
                default => '<fg=cyan>UNTRACKED</>',
            };

            $color = match ($diff->category) {
                'MISSING' => 'red',
                'MISMATCH' => 'yellow',
                default => 'cyan',
            };

            return [
                'status' => $statusBadge,
                'table' => "<fg={$color}>{$diff->table}</>",
                'attribute' => $diff->attribute,
                'expected' => $diff->expected,
                'actual' => $diff->actual,
                'issue' => $diff->issueType,
            ];
        }, $diffs);
    }
}
