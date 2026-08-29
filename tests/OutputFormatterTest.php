<?php

require __DIR__ . '/../vendor/autoload.php';

use EmirKefi\SchemaDrift\Data\SchemaDiff;
use EmirKefi\SchemaDrift\Services\OutputFormatter;

$formatter = new OutputFormatter();
$passed = 0;
$failed = 0;

function test(string $description, bool $condition, &$passed, &$failed): void
{
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "Running OutputFormatter Tests...\n";

// 1. Severity resolution
$errorDiff = new SchemaDiff('users', 'col: email', 'Present', 'Missing', 'MISSING_COLUMN');
test('MISSING_COLUMN has error severity', $errorDiff->severity === 'error', $passed, $failed);

$typeDiff = new SchemaDiff('users', 'col: status', 'string', 'integer', 'TYPE_MISMATCH');
test('TYPE_MISMATCH has error severity', $typeDiff->severity === 'error', $passed, $failed);

$warnDiff = new SchemaDiff('posts', '-', 'Missing', 'Present', 'UNTRACKED_TABLE');
test('UNTRACKED_TABLE has warning severity', $warnDiff->severity === 'warning', $passed, $failed);

$indexDiff = new SchemaDiff('users', 'index: idx_name', 'Present', 'Missing', 'MISSING_INDEX');
test('MISSING_INDEX has warning severity', $indexDiff->severity === 'warning', $passed, $failed);

// 2. JSON Formatter
$diffs = [$errorDiff, $warnDiff];
$jsonOutput = $formatter->formatJson($diffs);
$decoded = json_decode($jsonOutput, true);

test('JSON output parses correctly', is_array($decoded), $passed, $failed);
test('JSON contains total_issues count', ($decoded['total_issues'] ?? null) === 2, $passed, $failed);
test('JSON contains errors_count', ($decoded['errors_count'] ?? null) === 1, $passed, $failed);
test('JSON contains warnings_count', ($decoded['warnings_count'] ?? null) === 1, $passed, $failed);
test('JSON contains issues array with severity', isset($decoded['issues'][0]['severity']) && $decoded['issues'][0]['severity'] === 'error', $passed, $failed);

// 3. GitHub Actions Annotations Formatter
$githubOutput = $formatter->formatGithub($diffs);
test('GitHub output includes ::error', str_contains($githubOutput, '::error title=Schema Drift [MISSING_COLUMN]::Table \'users\' col: email -> Expected: Present, Actual: Missing'), $passed, $failed);
test('GitHub output includes ::warning', str_contains($githubOutput, '::warning title=Schema Drift [UNTRACKED_TABLE]::Table \'posts\' - -> Expected: Missing, Actual: Present'), $passed, $failed);

$githubEmpty = $formatter->formatGithub([]);
test('GitHub output for empty diffs shows notice', str_contains($githubEmpty, '::notice title=Schema Drift::Schema is in perfect sync with migrations.'), $passed, $failed);

// 4. Markdown Table Formatter
$mdOutput = $formatter->formatMarkdown($diffs);
test('Markdown output contains Markdown table header', str_contains($mdOutput, '| Severity | Table | Attribute | Expected (Migrations) | Actual (Database) | Issue Type |'), $passed, $failed);
test('Markdown output contains Error badge', str_contains($mdOutput, '🔴 Error') && str_contains($mdOutput, '`users`'), $passed, $failed);
test('Markdown output contains Warning badge', str_contains($mdOutput, '🟡 Warning') && str_contains($mdOutput, '`posts`'), $passed, $failed);

$mdEmpty = $formatter->formatMarkdown([]);
test('Markdown output for in-sync schemas shows success header', str_contains($mdEmpty, '✅ Schema Drift Detection') && str_contains($mdEmpty, 'No schema drift detected'), $passed, $failed);

// 5. Table Rows Formatter
$tableRows = $formatter->formatTableRows($diffs);
test('Table rows include severity formatting', count($tableRows) === 2 && str_contains($tableRows[0]['severity'], 'ERROR') && str_contains($tableRows[1]['severity'], 'WARN'), $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
