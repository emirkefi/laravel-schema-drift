<?php

require __DIR__ . '/../vendor/autoload.php';

use EmirKefi\SchemaDrift\Services\TypeNormalizer;

$normalizer = new TypeNormalizer();
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

echo "Running TypeNormalizer Tests...\n";

// MySQL Type Tests
test('MySQL tinyint(1) -> boolean', $normalizer->normalizeType('tinyint(1)', 'mysql') === 'boolean', $passed, $failed);
test('MySQL tinyint(4) -> integer', $normalizer->normalizeType('tinyint(4)', 'mysql') === 'integer', $passed, $failed);
test('MySQL int(11) unsigned -> integer', $normalizer->normalizeType('int(11) unsigned', 'mysql') === 'integer', $passed, $failed);
test('MySQL bigint(20) unsigned -> bigint', $normalizer->normalizeType('bigint(20) unsigned', 'mysql') === 'bigint', $passed, $failed);
test('MySQL varchar(255) -> string', $normalizer->normalizeType('varchar(255)', 'mysql') === 'string', $passed, $failed);
test('MySQL decimal(10,2) -> decimal', $normalizer->normalizeType('decimal(10,2)', 'mysql') === 'decimal', $passed, $failed);
test('MySQL datetime -> datetime', $normalizer->normalizeType('datetime', 'mysql') === 'datetime', $passed, $failed);
test('MySQL json -> json', $normalizer->normalizeType('json', 'mysql') === 'json', $passed, $failed);
test('MySQL longblob -> binary', $normalizer->normalizeType('longblob', 'mysql') === 'binary', $passed, $failed);

// PostgreSQL Type Tests
test('PostgreSQL bool -> boolean', $normalizer->normalizeType('bool', 'pgsql') === 'boolean', $passed, $failed);
test('PostgreSQL boolean -> boolean', $normalizer->normalizeType('boolean', 'pgsql') === 'boolean', $passed, $failed);
test('PostgreSQL int2 -> integer', $normalizer->normalizeType('int2', 'pgsql') === 'integer', $passed, $failed);
test('PostgreSQL int4 -> integer', $normalizer->normalizeType('int4', 'pgsql') === 'integer', $passed, $failed);
test('PostgreSQL int8 -> bigint', $normalizer->normalizeType('int8', 'pgsql') === 'bigint', $passed, $failed);
test('PostgreSQL character varying -> string', $normalizer->normalizeType('character varying', 'pgsql') === 'string', $passed, $failed);
test('PostgreSQL timestamp without time zone -> datetime', $normalizer->normalizeType('timestamp without time zone', 'pgsql') === 'datetime', $passed, $failed);
test('PostgreSQL jsonb -> json', $normalizer->normalizeType('jsonb', 'pgsql') === 'json', $passed, $failed);
test('PostgreSQL bytea -> binary', $normalizer->normalizeType('bytea', 'pgsql') === 'binary', $passed, $failed);

// SQL Server Type Tests
test('SQL Server bit -> boolean', $normalizer->normalizeType('bit', 'sqlsrv') === 'boolean', $passed, $failed);
test('SQL Server int -> integer', $normalizer->normalizeType('int', 'sqlsrv') === 'integer', $passed, $failed);
test('SQL Server bigint -> bigint', $normalizer->normalizeType('bigint', 'sqlsrv') === 'bigint', $passed, $failed);
test('SQL Server nvarchar(max) -> string', $normalizer->normalizeType('nvarchar(max)', 'sqlsrv') === 'string', $passed, $failed);
test('SQL Server datetime2 -> datetime', $normalizer->normalizeType('datetime2', 'sqlsrv') === 'datetime', $passed, $failed);

// SQLite Type Tests
test('SQLite integer -> integer', $normalizer->normalizeType('integer', 'sqlite') === 'integer', $passed, $failed);
test('SQLite varchar -> string', $normalizer->normalizeType('varchar', 'sqlite') === 'string', $passed, $failed);
test('SQLite numeric -> decimal', $normalizer->normalizeType('numeric', 'sqlite') === 'decimal', $passed, $failed);

// Default Value Tests
test("Postgres cast 'active'::character varying -> active", $normalizer->normalizeDefault("'active'::character varying", 'string', 'pgsql') === 'active', $passed, $failed);
test("Postgres cast 0::smallint -> 0", $normalizer->normalizeDefault('0::smallint', 'integer', 'pgsql') === '0', $passed, $failed);
test("Postgres boolean true -> 1", $normalizer->normalizeDefault('true', 'boolean', 'pgsql') === '1', $passed, $failed);
test("Postgres boolean false -> 0", $normalizer->normalizeDefault('false', 'boolean', 'pgsql') === '0', $passed, $failed);
test("Postgres NULL::character varying -> null", $normalizer->normalizeDefault('NULL::character varying', 'string', 'pgsql') === null, $passed, $failed);

test("SQL Server ((0)) -> 0", $normalizer->normalizeDefault('((0))', 'integer', 'sqlsrv') === '0', $passed, $failed);
test("SQL Server ((1)) boolean -> 1", $normalizer->normalizeDefault('((1))', 'boolean', 'sqlsrv') === '1', $passed, $failed);
test("SQL Server ('active') -> active", $normalizer->normalizeDefault("('active')", 'string', 'sqlsrv') === 'active', $passed, $failed);
test("SQL Server (N'test') -> test", $normalizer->normalizeDefault("(N'test')", 'string', 'sqlsrv') === 'test', $passed, $failed);
test("SQL Server (getdate()) -> CURRENT_TIMESTAMP", $normalizer->normalizeDefault('(getdate())', 'datetime', 'sqlsrv') === 'CURRENT_TIMESTAMP', $passed, $failed);
test("SQL Server (NULL) -> null", $normalizer->normalizeDefault('(NULL)', 'string', 'sqlsrv') === null, $passed, $failed);

test("MySQL b'1' -> 1", $normalizer->normalizeDefault("b'1'", 'boolean', 'mysql') === '1', $passed, $failed);
test("MySQL b'0' -> 0", $normalizer->normalizeDefault("b'0'", 'boolean', 'mysql') === '0', $passed, $failed);
test("MySQL 'active' -> active", $normalizer->normalizeDefault("'active'", 'string', 'mysql') === 'active', $passed, $failed);
test("MySQL current_timestamp() -> CURRENT_TIMESTAMP", $normalizer->normalizeDefault('current_timestamp()', 'datetime', 'mysql') === 'CURRENT_TIMESTAMP', $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
