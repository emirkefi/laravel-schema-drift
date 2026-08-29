<?php

namespace EmirKefi\SchemaDrift\Data;

class SchemaDiff
{
    public readonly string $severity; // 'error' or 'warning'
    public readonly string $category; // 'MISSING', 'MISMATCH', 'UNTRACKED'

    public function __construct(
        public readonly string $table,
        public readonly string $attribute,
        public readonly string $expected,
        public readonly string $actual,
        public readonly string $issueType,
        ?string $severity = null,
        ?string $category = null
    ) {
        $this->severity = $severity ?? $this->resolveSeverity($this->issueType);
        $this->category = $category ?? $this->resolveCategory($this->issueType);
    }

    protected function resolveSeverity(string $issueType): string
    {
        return match ($issueType) {
            'MISSING_TABLE', 'MISSING_COLUMN', 'TYPE_MISMATCH', 'NULLABILITY_MISMATCH', 'MISSING_FOREIGN_KEY' => 'error',
            default => 'warning',
        };
    }

    protected function resolveCategory(string $issueType): string
    {
        return match ($issueType) {
            'MISSING_TABLE', 'MISSING_COLUMN', 'MISSING_FOREIGN_KEY' => 'MISSING',
            'TYPE_MISMATCH', 'NULLABILITY_MISMATCH', 'DEFAULT_MISMATCH' => 'MISMATCH',
            default => 'UNTRACKED',
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
            'category' => $this->category,
        ];
    }
}