<?php

require __DIR__ . '/../vendor/autoload.php';

use EmirKefi\SchemaDrift\Commands\DriftCheckCommand;

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

echo "Running DriftCheckCommand Definition & Options Tests...\n";

$command = new DriftCheckCommand();
$definition = $command->getDefinition();

// 1. Verify Command Options exist
test('Has --connection option', $definition->hasOption('connection'), $passed, $failed);
test('Has --shadow-connection option', $definition->hasOption('shadow-connection'), $passed, $failed);
test('Has --path option', $definition->hasOption('path'), $passed, $failed);
test('Has --fresh-shadow option', $definition->hasOption('fresh-shadow'), $passed, $failed);

// 2. Verify Command Name & Description
test('Command name is schema:drift', $command->getName() === 'schema:drift', $passed, $failed);
test('Command has description', !empty($command->getDescription()), $passed, $failed);

echo "\nResults: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
