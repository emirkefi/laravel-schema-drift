<?php

namespace YourVendor\SchemaDrift\Data;

class SchemaDiff
{
    public function __construct(
        public readonly string $table,
        public readonly string $attribute,
        public readonly string $expected,
        public readonly string $actual,
        public readonly string $issueType // 'MISSING_COLUMN', 'EXTRA_COLUMN', 'TYPE_MISMATCH', etc.
    ) {}

    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'attribute' => $this->attribute,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'issue' => $this->issueType,
        ];
    }
}