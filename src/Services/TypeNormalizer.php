<?php

namespace EmirKefi\SchemaDrift\Services;

class TypeNormalizer
{
    /**
     * Map a raw dialect-specific column type into a canonical schema type.
     */
    public function normalizeType(string $rawType, string $driver = 'default'): string
    {
        $type = strtolower(trim($rawType));

        // Strip modifiers like unsigned, zerofill, binary
        $type = preg_replace('/\s+(unsigned|zerofill|binary)/i', '', $type);

        // Check for boolean types first (e.g., tinyint(1), bit(1), bool, boolean)
        if (
            $type === 'tinyint(1)' ||
            $type === 'bool' ||
            $type === 'boolean' ||
            $type === 'bit(1)' ||
            ($driver === 'sqlsrv' && $type === 'bit')
        ) {
            return 'boolean';
        }

        // Remove parameters / precision / length: e.g. varchar(255) -> varchar, int(11) -> int, datetime2(7) -> datetime2
        $baseType = preg_replace('/\s*\([^)]*\)/', '', $type);
        $baseType = trim(explode(' ', $baseType)[0]);

        return match ($baseType) {
            // Integers
            'int', 'integer', 'tinyint', 'smallint', 'mediumint',
            'int2', 'int4', 'serial', 'smallserial' => 'integer',

            // Big integers
            'bigint', 'int8', 'bigserial' => 'bigint',

            // Booleans
            'bit', 'bool', 'boolean' => 'boolean',

            // Decimals & Floats
            'decimal', 'numeric', 'float', 'double', 'real',
            'dec', 'fixed', 'money', 'smallmoney' => 'decimal',

            // Strings & Text
            'varchar', 'nvarchar', 'char', 'nchar', 'character',
            'text', 'tinytext', 'mediumtext', 'longtext', 'ntext',
            'string', 'clob', 'uuid', 'guid', 'uniqueidentifier',
            'ulid', 'enum', 'set', 'citext', 'inet', 'cidr', 'macaddr' => 'string',

            // Dates & Times
            'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset',
            'timestamp', 'timestamptz', 'date', 'time',
            'timetz', 'year' => 'datetime',

            // JSON
            'json', 'jsonb' => 'json',

            // Binary
            'blob', 'tinyblob', 'mediumblob', 'longblob',
            'binary', 'varbinary', 'bytea', 'image', 'rowversion' => 'binary',

            default => $baseType,
        };
    }

    /**
     * Normalize a dialect-specific default value to a canonical string representation or null.
     */
    public function normalizeDefault(mixed $default, string $canonicalType = 'string', string $driver = 'default'): ?string
    {
        if ($default === null) {
            return null;
        }

        $val = trim((string) $default);

        if ($val === '' || strcasecmp($val, 'null') === 0) {
            return null;
        }

        // Recursively unwrap outer parentheses (SQL Server `((0))`, `('active')`, `(getdate())`)
        while (preg_match('/^\((.*)\)$/s', $val, $matches)) {
            $val = trim($matches[1]);
        }

        // Strip Postgres type casts, e.g. `'active'::character varying`, `0::smallint`, `NULL::text`
        if (preg_match('/^(.*?)::[a-zA-Z0-9_\s"]+$/', $val, $matches)) {
            $val = trim($matches[1]);
        }

        if (strcasecmp($val, 'null') === 0) {
            return null;
        }

        // Strip SQL Server Unicode literal prefix: N'text' -> 'text'
        if (preg_match('/^N\'(.*)\'$/si', $val, $matches)) {
            $val = "'" . $matches[1] . "'";
        }

        // Strip MySQL bit literals: b'0' -> '0', b'1' -> '1'
        if (preg_match('/^b\'([01])\'$/i', $val, $matches)) {
            $val = $matches[1];
        }

        // Normalize dynamic datetime defaults
        if (in_array(strtolower($val), ['current_timestamp', 'current_timestamp()', 'now()', 'getdate()'], true)) {
            return 'CURRENT_TIMESTAMP';
        }

        // Strip enclosing quotes if string literal
        if ((str_starts_with($val, "'") && str_ends_with($val, "'")) ||
            (str_starts_with($val, '"') && str_ends_with($val, '"'))) {
            $val = substr($val, 1, -1);
        }

        // Type-specific canonical conversions
        if ($canonicalType === 'boolean') {
            $lower = strtolower($val);
            if (in_array($lower, ['1', 'true', 't'], true)) {
                return '1';
            }
            if (in_array($lower, ['0', 'false', 'f'], true)) {
                return '0';
            }
        }

        if ($canonicalType === 'integer' || $canonicalType === 'bigint') {
            if (is_numeric($val)) {
                return (string) ((int) $val);
            }
        }

        if ($canonicalType === 'decimal') {
            if (is_numeric($val)) {
                return (string) ((float) $val);
            }
        }

        return $val;
    }

    /**
     * Normalize raw column metadata extracted from database.
     */
    public function normalizeColumn(array $column, string $driver = 'default'): array
    {
        $rawType = strtolower($column['type_name'] ?? $column['type'] ?? 'unknown');
        $canonicalType = $this->normalizeType($rawType, $driver);
        $rawDefault = $column['default'] ?? null;
        $canonicalDefault = $this->normalizeDefault($rawDefault, $canonicalType, $driver);

        return [
            'name' => $column['name'] ?? '',
            'type' => $canonicalType,
            'raw_type' => $rawType,
            'nullable' => (bool) ($column['nullable'] ?? false),
            'default' => $canonicalDefault,
            'raw_default' => $rawDefault,
        ];
    }
}
