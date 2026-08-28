<?php

namespace EmirKefi\SchemaDrift\Services;

use EmirKefi\SchemaDrift\Data\SchemaDiff;

class MigrationGenerator
{
    /**
     * Generate the PHP migration file content from schema diffs.
     *
     * @param array<SchemaDiff> $diffs
     */
    public function generate(array $diffs, array $liveSchema, array $expectedSchema = [], bool $destructive = false): string
    {
        $upOperations = [];
        $downOperations = [];

        $untrackedTables = [];
        $missingTables = [];
        $tableDiffs = [];

        foreach ($diffs as $diff) {
            if ($diff->issueType === 'UNTRACKED_TABLE') {
                $untrackedTables[] = $diff->table;
            } elseif ($diff->issueType === 'MISSING_TABLE') {
                $missingTables[] = $diff->table;
            } else {
                $tableDiffs[$diff->table][] = $diff;
            }
        }

        // 1. Untracked Tables -> Schema::create
        foreach ($untrackedTables as $table) {
            $tableData = $liveSchema[$table] ?? null;
            if (!$tableData) {
                continue;
            }

            $colLines = [];
            foreach ($tableData['columns'] as $colName => $col) {
                $colLines[] = '            ' . $this->renderColumnDefinition($colName, $col);
            }

            // Indexes
            foreach ($tableData['indexes'] ?? [] as $index) {
                if ($index['primary'] ?? false) {
                    continue; // Handled by primary key or id column
                }
                $colsFormatted = "['" . implode("', '", $index['columns']) . "']";
                if ($index['unique'] ?? false) {
                    $colLines[] = "            \$table->unique({$colsFormatted});";
                } else {
                    $colLines[] = "            \$table->index({$colsFormatted});";
                }
            }

            $body = implode("\n", $colLines);
            $upOperations[] = <<<PHP
        Schema::create('{$table}', function (Blueprint \$table) {
{$body}
        });
PHP;
            $downOperations[] = "        Schema::dropIfExists('{$table}');";
        }

        // 2. Missing Tables (Destructive)
        foreach ($missingTables as $table) {
            if ($destructive) {
                $upOperations[] = "        Schema::dropIfExists('{$table}');";
            } else {
                $upOperations[] = "        // Note: Table '{$table}' is missing in live DB. Re-run with --destructive to drop in migration.";
            }
        }

        // 3. Table Column / Index Modifications -> Schema::table
        foreach ($tableDiffs as $table => $diffList) {
            $colLines = [];
            $handledModifiedCols = [];

            foreach ($diffList as $diff) {
                $attribute = $diff->attribute;
                $colName = preg_replace('/^(col:\s*|index:\s*|fk:\s*)/', '', $attribute);
                $colName = trim(explode(' ', $colName)[0]);

                if ($diff->issueType === 'UNTRACKED_COLUMN') {
                    $colData = $liveSchema[$table]['columns'][$colName] ?? null;
                    if ($colData) {
                        $colLines[] = '            ' . $this->renderColumnDefinition($colName, $colData);
                    }
                } elseif (in_array($diff->issueType, ['TYPE_MISMATCH', 'NULLABILITY_MISMATCH', 'DEFAULT_MISMATCH'], true)) {
                    if (!isset($handledModifiedCols[$colName])) {
                        $handledModifiedCols[$colName] = true;
                        $colData = $liveSchema[$table]['columns'][$colName] ?? null;
                        if ($colData) {
                            $colLines[] = '            ' . $this->renderColumnDefinition($colName, $colData, true);
                        }
                    }
                } elseif ($diff->issueType === 'MISSING_COLUMN') {
                    if ($destructive) {
                        $colLines[] = "            \$table->dropColumn('{$colName}');";
                    } else {
                        $colLines[] = "            // Note: Column '{$colName}' is missing in live DB. Re-run with --destructive to drop.";
                    }
                } elseif ($diff->issueType === 'MISSING_INDEX') {
                    $idxName = $colName;
                    $colLines[] = "            // Missing index: {$idxName}";
                }
            }

            if (!empty($colLines)) {
                $body = implode("\n", $colLines);
                $upOperations[] = <<<PHP
        Schema::table('{$table}', function (Blueprint \$table) {
{$body}
        });
PHP;
            }
        }

        $upBody = empty($upOperations) ? '        // No schema fixes required' : implode("\n\n", $upOperations);
        $downBody = empty($downOperations) ? '        // Reverse migration operations' : implode("\n\n", $downOperations);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
{$upBody}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downBody}
    }
};

PHP;
    }

    /**
     * Save migration content to a timestamped file in migrations folder.
     */
    public function write(string $content, ?string $directory = null, string $name = 'fix_schema_drift'): string
    {
        $dir = $directory ?? (function_exists('database_path') ? database_path('migrations') : getcwd() . '/database/migrations');
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filePath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($filePath, $content);

        return $filePath;
    }

    /**
     * Render a Fluent Blueprint column method string.
     */
    public function renderColumnDefinition(string $name, array $column, bool $isChange = false): string
    {
        $rawType = strtolower($column['raw_type'] ?? $column['type'] ?? 'string');
        $canonicalType = $column['type'] ?? 'string';
        $nullable = (bool) ($column['nullable'] ?? false);
        $default = $column['default'] ?? null;

        // Auto-increment primary id
        if ($name === 'id' && ($canonicalType === 'bigint' || $canonicalType === 'integer')) {
            if ($canonicalType === 'bigint') {
                return "\$table->id();";
            }
            return "\$table->increments('id');";
        }

        // Determine Blueprint method
        $method = match ($canonicalType) {
            'boolean' => 'boolean',
            'bigint' => str_contains($rawType, 'unsigned') ? 'unsignedBigInteger' : 'bigInteger',
            'integer' => match (true) {
                str_contains($rawType, 'tinyint') => str_contains($rawType, 'unsigned') ? 'unsignedTinyInteger' : 'tinyInteger',
                str_contains($rawType, 'smallint') => str_contains($rawType, 'unsigned') ? 'unsignedSmallInteger' : 'smallInteger',
                str_contains($rawType, 'mediumint') => str_contains($rawType, 'unsigned') ? 'unsignedMediumInteger' : 'mediumInteger',
                str_contains($rawType, 'unsigned') => 'unsignedInteger',
                default => 'integer',
            },
            'decimal' => match (true) {
                str_contains($rawType, 'float') => 'float',
                str_contains($rawType, 'double') => 'double',
                default => 'decimal',
            },
            'string' => match (true) {
                str_contains($rawType, 'longtext') => 'longText',
                str_contains($rawType, 'mediumtext') => 'mediumText',
                str_contains($rawType, 'tinytext') => 'tinyText',
                str_contains($rawType, 'text') => 'text',
                str_contains($rawType, 'char') && !str_contains($rawType, 'varchar') => 'char',
                str_contains($rawType, 'uuid') => 'uuid',
                str_contains($rawType, 'ulid') => 'ulid',
                default => 'string',
            },
            'datetime' => match (true) {
                str_contains($rawType, 'timestamp') => 'timestamp',
                str_contains($rawType, 'date') && !str_contains($rawType, 'datetime') => 'date',
                str_contains($rawType, 'time') && !str_contains($rawType, 'datetime') && !str_contains($rawType, 'timestamp') => 'time',
                str_contains($rawType, 'year') => 'year',
                default => 'dateTime',
            },
            'json' => 'json',
            'binary' => match (true) {
                str_contains($rawType, 'mediumblob') => 'mediumBinary',
                str_contains($rawType, 'longblob') => 'longBinary',
                default => 'binary',
            },
            default => 'string',
        };

        $definition = "\$table->{$method}('{$name}')";

        if ($nullable) {
            $definition .= '->nullable()';
        }

        if ($default !== null) {
            if ($canonicalType === 'boolean') {
                $defVal = in_array($default, ['1', 'true', true], true) ? 'true' : 'false';
                $definition .= "->default({$defVal})";
            } elseif (is_numeric($default) && ($canonicalType === 'integer' || $canonicalType === 'bigint' || $canonicalType === 'decimal')) {
                $definition .= "->default({$default})";
            } else {
                $escaped = addslashes((string) $default);
                $definition .= "->default('{$escaped}')";
            }
        }

        if ($isChange) {
            $definition .= '->change()';
        }

        return $definition . ';';
    }
}
