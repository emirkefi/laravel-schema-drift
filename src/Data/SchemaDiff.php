<?php

namespace EmirKefi\SchemaDrift\Data;

class SchemaDiff
{
    public readonly string $severity; // 'error' or 'warning'

    public function __construct(
        public readonly string $table,
        public readonly string $attribute,
        public readonly string $expected,
        public readonly string $actual,
        public readonly string $issueType, // 'MISSING_COLUMN', 'UNTRACKED_COLUMN', 'TYPE_MISMATCH', etc.
        ?string $severity = null
    ) {
        $this->severity = $severity ?? $this->resolveSeverity($this->issueType);
    }

    protected function resolveSeverity(string $issueType): string
    {
        return match ($issueType) {
            'MISSING_TABLE', 'MISSING_COLUMN', 'TYPE_MISMATCH', 'NULLABILITY_MISMATCH', 'MISSING_FOREIGN_KEY' => 'error',
            default => 'warning', // UNTRACKED_TABLE, UNTRACKED_COLUMN, DEFAULT_MISMATCH, MISSING_INDEX
        };
    }

    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'attribute' => $this->attribute,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'issue' => $this->issueType,
            'severity' => $this->severity,
        ];
    }
}