<?php

require __DIR__ . '/../vendor/autoload.php';

use EmirKefi\SchemaDrift\Commands\DriftCheckCommand;
use EmirKefi\SchemaDrift\Commands\GenerateMigrationCommand;

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

echo "Running DriftCheckCommand & GenerateMigrationCommand Definition Tests...\n";

// 1. DriftCheckCommand
$driftCommand = new DriftCheckCommand();
$driftDef = $driftCommand->getDefinition();

test('DriftCheckCommand has --connection', $driftDef->hasOption('connection'), $passed, $failed);
test('DriftCheckCommand has --shadow-connection', $driftDef->hasOption('shadow-connection'), $passed, $failed);
test('DriftCheckCommand has --path', $driftDef->hasOption('path'), $passed, $failed);
test('DriftCheckCommand has --fresh-shadow', $driftDef->hasOption('fresh-shadow'), $passed, $failed);
test('DriftCheckCommand has --fix', $driftDef->hasOption('fix'), $passed, $failed);
test('DriftCheckCommand has --destructive', $driftDef->hasOption('destructive'), $passed, $failed);

// 2. GenerateMigrationCommand
$genCommand = new GenerateMigrationCommand();
$genDef = $genCommand->getDefinition();

test('GenerateMigrationCommand name is schema:drift:generate-migration', $genCommand->getName() === 'schema:drift:generate-migration', $passed, $failed);
test('GenerateMigrationCommand has --connection', $genDef->hasOption('connection'), $passed, $failed);
test('GenerateMigrationCommand has --shadow-connection', $genDef->hasOption('shadow-connection'), $passed, $failed);
test('GenerateMigrationCommand has --output', $genDef->hasOption('output'), $passed, $failed);
test('GenerateMigrationCommand has --destructive', $genDef->hasOption('destructive'), $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
