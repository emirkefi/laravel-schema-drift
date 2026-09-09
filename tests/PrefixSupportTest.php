<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use EmirKefi\SchemaDrift\Extractors\SchemaExtractor;
use EmirKefi\SchemaDrift\Services\DiffEngine;

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

echo "Running PrefixSupport Tests...\n";

// Set up the Illuminate Container and Facades
$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);

class SimpleConfig implements ArrayAccess
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->items, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        data_set($this->items, $key, $value);
    }

    public function offsetExists(mixed $offset): bool
    {
        return data_get($this->items, $offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return data_get($this->items, $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        data_set($this->items, $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        data_set($this->items, $offset, null);
    }
}

$config = new SimpleConfig([
    'database' => [
        'default' => 'prefixed',
        'connections' => [
            'prefixed' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'drift_',
                'prefix_indexes' => true,
                'foreign_key_constraints' => true,
            ],
            'shadow' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ],
    ],
    'schema-drift' => [
        'ignore_tables' => [],
        'check_indexes' => true,
        'check_foreign_keys' => true,
        'check_types' => true,
        'check_defaults' => true,
    ],
]);

$app->instance('config', $config);

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        global $config;
        if ($key === null) {
            return $config;
        }
        return $config->get($key, $default);
    }
}

$app->singleton('db.factory', function ($app) {
    return new ConnectionFactory($app);
});

$app->singleton('db', function ($app) {
    return new DatabaseManager($app, $app['db.factory']);
});

// 1. Connection Prefix Detection
$conn = DB::connection('prefixed');
test('Connection prefix is correctly detected as drift_', $conn->getTablePrefix() === 'drift_', $passed, $failed);

// 2. Migration execution on prefixed database
// Blueprint declares logical names ('users', 'posts') without manual prefixes
Schema::connection('prefixed')->create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});

Schema::connection('prefixed')->create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('title')->index();
    $table->timestamps();
});

// Verify physical tables in the database carry the prefix 'drift_'
$rawTables = Schema::connection('prefixed')->getTables();
$rawTableNames = array_map(fn($t) => $t['name'] ?? $t, $rawTables);
test('Physical database tables carry drift_ prefix', in_array('drift_users', $rawTableNames, true) && in_array('drift_posts', $rawTableNames, true), $passed, $failed);

// 3. SchemaExtractor normalizes physical prefixed tables to logical names
$liveExtractor = new SchemaExtractor('prefixed');
$liveSnapshot = $liveExtractor->extract();

test('Extracted snapshot contains normalized logical table names (users, posts)', isset($liveSnapshot['users']) && isset($liveSnapshot['posts']) && !isset($liveSnapshot['drift_users']) && !isset($liveSnapshot['drift_posts']), $passed, $failed);
test('Columns are populated correctly for users table', isset($liveSnapshot['users']['columns']['id'], $liveSnapshot['users']['columns']['name'], $liveSnapshot['users']['columns']['email']), $passed, $failed);
test('Columns are populated correctly for posts table', isset($liveSnapshot['posts']['columns']['id'], $liveSnapshot['posts']['columns']['user_id'], $liveSnapshot['posts']['columns']['title']), $passed, $failed);

// 4. Foreign Key normalization: foreign_table points to logical table name
$postFks = $liveSnapshot['posts']['foreign_keys'];
$firstFk = reset($postFks);
test('Foreign key foreign_table is normalized to logical table "users"', ($firstFk['foreign_table'] ?? null) === 'users', $passed, $failed);

// 5. Index normalization: index prefix is normalized
$postIndexes = array_keys($liveSnapshot['posts']['indexes']);
test('Index names are normalized without drift_ prefix', in_array('posts_title_index', $postIndexes, true) && !in_array('drift_posts_title_index', $postIndexes, true), $passed, $failed);

// 6. Simulate migration on shadow database (un-prefixed) and verify 0 drift detected
Schema::connection('shadow')->create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});

Schema::connection('shadow')->create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('title')->index();
    $table->timestamps();
});

$shadowExtractor = new SchemaExtractor('shadow');
$expectedSnapshot = $shadowExtractor->extract();

$diffEngine = new DiffEngine();
$diffs = $diffEngine->compare($liveSnapshot, $expectedSnapshot);
test('Prefixed live DB vs un-prefixed migration schema detects 0 drift/mismatches', count($diffs) === 0, $passed, $failed);

// 7. Un-prefixed tables created outside the prefix pattern are not corrupted
Schema::connection('prefixed')->getConnection()->statement('CREATE TABLE unpfx_legacy (id INTEGER PRIMARY KEY, code TEXT)');
$snapshotWithLegacy = $liveExtractor->extract();
test('Raw un-prefixed table name is preserved without corruption', isset($snapshotWithLegacy['unpfx_legacy']), $passed, $failed);
test('Columns of un-prefixed table are correctly introspected', isset($snapshotWithLegacy['unpfx_legacy']['columns']['id'], $snapshotWithLegacy['unpfx_legacy']['columns']['code']), $passed, $failed);

// 8. Ignore table configuration works for both logical and prefixed names
$config->set('schema-drift.ignore_tables', ['posts', 'drift_users']);
$filteredSnapshot = $liveExtractor->extract();
test('Ignore list matches both logical ("posts") and physical ("drift_users") table names', !isset($filteredSnapshot['posts']) && !isset($filteredSnapshot['users']) && isset($filteredSnapshot['unpfx_legacy']), $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
