<?php

require __DIR__ . '/../vendor/autoload.php';

use EmirKefi\SchemaDrift\Data\SchemaDiff;
use EmirKefi\SchemaDrift\Services\MigrationGenerator;

$generator = new MigrationGenerator();
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

echo "Running MigrationGenerator Tests...\n";

// 1. Column definitions
$col1 = ['type' => 'string', 'raw_type' => 'varchar(255)', 'nullable' => true, 'default' => 'draft'];
$rendered1 = $generator->renderColumnDefinition('status', $col1);
test("Renders string with nullable and default", $rendered1 === "\$table->string('status')->nullable()->default('draft');", $passed, $failed);

$col2 = ['type' => 'boolean', 'raw_type' => 'tinyint(1)', 'nullable' => false, 'default' => '1'];
$rendered2 = $generator->renderColumnDefinition('is_active', $col2);
test("Renders boolean with default true", $rendered2 === "\$table->boolean('is_active')->default(true);", $passed, $failed);

$col3 = ['type' => 'integer', 'raw_type' => 'int(11) unsigned', 'nullable' => false, 'default' => 0];
$rendered3 = $generator->renderColumnDefinition('views_count', $col3);
test("Renders unsignedInteger with default 0", $rendered3 === "\$table->unsignedInteger('views_count')->default(0);", $passed, $failed);

$col4 = ['type' => 'bigint', 'raw_type' => 'bigint(20) unsigned', 'nullable' => false, 'default' => null];
$rendered4 = $generator->renderColumnDefinition('id', $col4);
test("Renders primary bigIncrements id for new column", $rendered4 === "\$table->id();", $passed, $failed);

$col4Change = ['type' => 'bigint', 'raw_type' => 'bigint(20) unsigned', 'nullable' => false, 'default' => null];
$rendered4Change = $generator->renderColumnDefinition('id', $col4Change, true);
test("Renders unsignedBigInteger change for existing id column", $rendered4Change === "\$table->unsignedBigInteger('id')->change();", $passed, $failed);

$col5 = ['type' => 'string', 'raw_type' => 'varchar(100)', 'nullable' => false, 'default' => null];
$rendered5 = $generator->renderColumnDefinition('phone', $col5, true);
test("Renders change() modifier", $rendered5 === "\$table->string('phone')->change();", $passed, $failed);

// 2. Untracked Table Generation
$diffs = [
    new SchemaDiff('posts', '-', 'Missing in Migrations', 'Present in DB', 'UNTRACKED_TABLE'),
];
$liveSchema = [
    'posts' => [
        'columns' => [
            'id' => ['type' => 'bigint', 'raw_type' => 'bigint unsigned', 'nullable' => false, 'default' => null],
            'title' => ['type' => 'string', 'raw_type' => 'varchar(255)', 'nullable' => false, 'default' => null],
            'is_published' => ['type' => 'boolean', 'raw_type' => 'tinyint(1)', 'nullable' => false, 'default' => '0'],
        ],
        'indexes' => [
            'posts_title_unique' => ['columns' => ['title'], 'unique' => true, 'primary' => false],
        ],
    ],
];
$code = $generator->generate($diffs, $liveSchema);
test("Generates Schema::create for untracked table", str_contains($code, "Schema::create('posts'") && str_contains($code, "\$table->id();") && str_contains($code, "\$table->string('title');") && str_contains($code, "\$table->unique(['title']);"), $passed, $failed);
test("Generates down method Schema::dropIfExists", str_contains($code, "Schema::dropIfExists('posts');"), $passed, $failed);

// 3. Column Modifications and Untracked Columns
$diffsCol = [
    new SchemaDiff('users', 'col: bio', 'Missing', 'Present', 'UNTRACKED_COLUMN'),
    new SchemaDiff('users', 'col: role (type)', 'string', 'integer', 'TYPE_MISMATCH'),
];
$liveSchemaUsers = [
    'users' => [
        'columns' => [
            'bio' => ['type' => 'string', 'raw_type' => 'text', 'nullable' => true, 'default' => null],
            'role' => ['type' => 'integer', 'raw_type' => 'int(11)', 'nullable' => false, 'default' => 1],
        ],
    ],
];
$codeCols = $generator->generate($diffsCol, $liveSchemaUsers);
test("Generates Schema::table for untracked column", str_contains($codeCols, "Schema::table('users'") && str_contains($codeCols, "\$table->text('bio')->nullable();"), $passed, $failed);
test("Generates change() for type mismatch", str_contains($codeCols, "\$table->integer('role')->default(1)->change();"), $passed, $failed);

// 4. Missing Column / Table Destructive & Non-Destructive
$diffsMissing = [
    new SchemaDiff('users', 'col: old_token', 'Present', 'Missing', 'MISSING_COLUMN'),
    new SchemaDiff('legacy_table', '-', 'Present', 'Missing', 'MISSING_TABLE'),
];
$codeNonDestructive = $generator->generate($diffsMissing, []);
test("Non-destructive comments out missing drops", str_contains($codeNonDestructive, "Re-run with --destructive"), $passed, $failed);

$codeDestructive = $generator->generate($diffsMissing, [], [], true);
test("Destructive generates dropColumn and dropIfExists", str_contains($codeDestructive, "\$table->dropColumn('old_token');") && str_contains($codeDestructive, "Schema::dropIfExists('legacy_table');"), $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
