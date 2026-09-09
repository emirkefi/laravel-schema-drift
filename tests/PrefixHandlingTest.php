<?php

require __DIR__ . '/../vendor/autoload.php';

use EmirKefi\SchemaDrift\Extractors\SchemaExtractor;
use EmirKefi\SchemaDrift\Services\MigrationGenerator;
use EmirKefi\SchemaDrift\Data\SchemaDiff;

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

echo "Running PrefixHandling Tests...\n";

// Helper class to expose protected methods of SchemaExtractor for unit testing
class TestableSchemaExtractor extends SchemaExtractor
{
    public function testNormalizeIndexes(array $indexes, string $prefix = '', bool $prefixIndexes = false): array
    {
        return $this->normalizeIndexes($indexes, $prefix, $prefixIndexes);
    }

    public function testNormalizeForeignKeys(array $foreignKeys, string $prefix = ''): array
    {
        return $this->normalizeForeignKeys($foreignKeys, $prefix);
    }

    public function testResolveLogicalTableName(string $rawTableName, string $prefix): array
    {
        $hasPrefix = (!empty($prefix) && str_starts_with($rawTableName, $prefix));
        $logicalTableName = $hasPrefix ? substr($rawTableName, strlen($prefix)) : $rawTableName;
        return [
            'hasPrefix' => $hasPrefix,
            'logicalTableName' => $logicalTableName,
        ];
    }
}

$extractor = new TestableSchemaExtractor('default');

// 1. Prefix-like collisions: prefix is 'app_'
$prefix = 'app_';

// 1a. Table 'apps' does NOT start with 'app_'
$resApps = $extractor->testResolveLogicalTableName('apps', $prefix);
test("Table 'apps' is NOT stripped with prefix 'app_'", $resApps['hasPrefix'] === false && $resApps['logicalTableName'] === 'apps', $passed, $failed);

// 1b. Table 'application_settings' does NOT start with 'app_'
$resAppSettings = $extractor->testResolveLogicalTableName('application_settings', $prefix);
test("Table 'application_settings' is NOT stripped with prefix 'app_'", $resAppSettings['hasPrefix'] === false && $resAppSettings['logicalTableName'] === 'application_settings', $passed, $failed);

// 1c. Table 'app_users' DOES start with 'app_'
$resUsers = $extractor->testResolveLogicalTableName('app_users', $prefix);
test("Table 'app_users' is correctly stripped to 'users'", $resUsers['hasPrefix'] === true && $resUsers['logicalTableName'] === 'users', $passed, $failed);

// 2. Tables without the prefix: 'legacy_logs'
$resLegacy = $extractor->testResolveLogicalTableName('legacy_logs', $prefix);
test("Table 'legacy_logs' retains full literal name", $resLegacy['hasPrefix'] === false && $resLegacy['logicalTableName'] === 'legacy_logs', $passed, $failed);

// 3. Foreign key constraints: ensure foreign table does not end up double-prefixed
$rawFks = [
    [
        'name' => 'app_posts_user_id_foreign',
        'columns' => ['user_id'],
        'foreign_table' => 'app_users',
        'foreign_columns' => ['id'],
    ],
];
$normalizedFks = $extractor->testNormalizeForeignKeys($rawFks, $prefix);
test("FK foreign_table is normalized from 'app_users' to 'users'", isset($normalizedFks['posts_user_id_foreign']) && $normalizedFks['posts_user_id_foreign']['foreign_table'] === 'users', $passed, $failed);

// Test migration generation for foreign key
$generator = new MigrationGenerator();
$liveSchemaWithFk = [
    'posts' => [
        'columns' => [
            'id' => ['type' => 'bigint', 'nullable' => false, 'default' => null],
            'user_id' => ['type' => 'bigint', 'nullable' => false, 'default' => null],
        ],
        'indexes' => [],
        'foreign_keys' => $normalizedFks,
    ],
];
$diffsFk = [
    new SchemaDiff('posts', '-', 'Missing in Migrations', 'Present in DB', 'UNTRACKED_TABLE'),
];
$migrationCode = $generator->generate($diffsFk, $liveSchemaWithFk);
test("Migration code generates references('id')->on('users'), NOT on('app_users')", str_contains($migrationCode, "->on('users')") && !str_contains($migrationCode, "->on('app_users')"), $passed, $failed);

// 4. Custom or prefixed index names: prefix_indexes is true
$rawIndexes = [
    [
        'name' => 'app_users_email_unique',
        'columns' => ['email'],
        'unique' => true,
        'primary' => false,
    ],
    [
        'name' => 'custom_idx_status',
        'columns' => ['status'],
        'unique' => false,
        'primary' => false,
    ],
];

// 4a. When prefix_indexes is true:
$normalizedWithPrefixIndexes = $extractor->testNormalizeIndexes($rawIndexes, $prefix, true);
test("Index 'app_users_email_unique' is normalized to 'users_email_unique' when prefix_indexes=true", isset($normalizedWithPrefixIndexes['users_email_unique']), $passed, $failed);
test("Custom index 'custom_idx_status' without prefix is preserved", isset($normalizedWithPrefixIndexes['custom_idx_status']), $passed, $failed);

// 4b. When prefix_indexes is false:
$normalizedWithoutPrefixIndexes = $extractor->testNormalizeIndexes($rawIndexes, $prefix, false);
test("Index names preserved literally when prefix_indexes=false", isset($normalizedWithoutPrefixIndexes['app_users_email_unique']), $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
