<?php

require __DIR__ . '/../vendor/autoload.php';

use EmirKefi\SchemaDrift\Services\DiffEngine;

$diffEngine = new DiffEngine();
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

echo "Running DiffEngine Tests...\n";

// 1. Identical Schemas
$live = [
    'users' => [
        'columns' => [
            'id' => ['type' => 'bigint', 'nullable' => false, 'default' => null],
            'email' => ['type' => 'string', 'nullable' => false, 'default' => null],
            'is_active' => ['type' => 'boolean', 'nullable' => false, 'default' => '1'],
        ],
        'indexes' => [
            'users_email_unique' => ['columns' => ['email'], 'unique' => true, 'primary' => false],
        ],
        'foreign_keys' => [],
    ],
];

$expected = [
    'users' => [
        'columns' => [
            'id' => ['type' => 'bigint', 'nullable' => false, 'default' => null],
            'email' => ['type' => 'string', 'nullable' => false, 'default' => null],
            'is_active' => ['type' => 'boolean', 'nullable' => false, 'default' => '1'],
        ],
        'indexes' => [
            'users_email_unique' => ['columns' => ['email'], 'unique' => true, 'primary' => false],
        ],
        'foreign_keys' => [],
    ],
];

$diffs = $diffEngine->compare($live, $expected);
test('Identical schemas produce zero diffs', count($diffs) === 0, $passed, $failed);

// 2. Cross-Database SQLite integer vs MySQL bigint Compatibility (Primary Keys / BigInts)
$expectedSqlite = $expected;
$expectedSqlite['users']['columns']['id']['type'] = 'integer'; // SQLite reports integer for $table->id()
$diffsCompat = $diffEngine->compare($live, $expectedSqlite);
test('SQLite integer vs MySQL bigint is recognized as compatible', count($diffsCompat) === 0, $passed, $failed);

// 3. True Type Mismatch
$liveTypeMismatch = $live;
$liveTypeMismatch['users']['columns']['is_active']['type'] = 'string';
$diffs = $diffEngine->compare($liveTypeMismatch, $expected);
test('Detects TYPE_MISMATCH correctly for incompatible types', count($diffs) === 1 && $diffs[0]->issueType === 'TYPE_MISMATCH' && $diffs[0]->expected === 'boolean' && $diffs[0]->actual === 'string', $passed, $failed);

// 4. Default Mismatch
$liveDefaultMismatch = $live;
$liveDefaultMismatch['users']['columns']['is_active']['default'] = '0';
$diffs = $diffEngine->compare($liveDefaultMismatch, $expected);
test('Detects DEFAULT_MISMATCH correctly', count($diffs) === 1 && $diffs[0]->issueType === 'DEFAULT_MISMATCH' && $diffs[0]->expected === '1' && $diffs[0]->actual === '0', $passed, $failed);

// 5. Nullability Mismatch
$liveNullMismatch = $live;
$liveNullMismatch['users']['columns']['email']['nullable'] = true;
$diffs = $diffEngine->compare($liveNullMismatch, $expected);
test('Detects NULLABILITY_MISMATCH correctly', count($diffs) === 1 && $diffs[0]->issueType === 'NULLABILITY_MISMATCH', $passed, $failed);

// 6. Missing Column in DB
$liveMissingCol = $live;
unset($liveMissingCol['users']['columns']['email']);
$diffs = $diffEngine->compare($liveMissingCol, $expected);
test('Detects MISSING_COLUMN correctly', count($diffs) === 1 && $diffs[0]->issueType === 'MISSING_COLUMN', $passed, $failed);

// 7. Untracked Column in DB
$liveUntrackedCol = $live;
$liveUntrackedCol['users']['columns']['legacy_field'] = ['type' => 'string', 'nullable' => true, 'default' => null];
$diffs = $diffEngine->compare($liveUntrackedCol, $expected);
test('Detects UNTRACKED_COLUMN correctly', count($diffs) === 1 && $diffs[0]->issueType === 'UNTRACKED_COLUMN', $passed, $failed);

// 8. Missing Table in DB
$liveMissingTable = [];
$diffs = $diffEngine->compare($liveMissingTable, $expected);
test('Detects MISSING_TABLE correctly', count($diffs) === 1 && $diffs[0]->issueType === 'MISSING_TABLE', $passed, $failed);

// 9. Untracked Table in DB
$expectedEmpty = [];
$diffs = $diffEngine->compare($live, $expectedEmpty);
test('Detects UNTRACKED_TABLE correctly', count($diffs) === 1 && $diffs[0]->issueType === 'UNTRACKED_TABLE', $passed, $failed);

// 10. Missing Index
$liveMissingIdx = $live;
$liveMissingIdx['users']['indexes'] = [];
$diffs = $diffEngine->compare($liveMissingIdx, $expected);
test('Detects MISSING_INDEX correctly', count($diffs) === 1 && $diffs[0]->issueType === 'MISSING_INDEX', $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
