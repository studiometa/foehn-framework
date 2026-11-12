<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

/**
 * What an export does with one table: keep its rows or not, which rows, and which
 * columns it replaces.
 *
 * Several rules can cover one table — a core rule, a plugin preset, the project
 * config — and {@see TableRule::merge()} combines them. The stricter side always
 * wins: no rows beats rows, and conditions add up.
 */
final readonly class TableRule
{
    /**
     * @param bool $rows Whether rows are exported. False exports the structure only.
     * @param list<string> $where SQL conditions a row must meet, joined with AND.
     * @param array<string, Anonymizer> $columns The replacement of each column.
     * @param string|null $seedColumn The column whose value seeds the replacements,
     *                                usually the primary key. Null hashes the value.
     * @param string|null $emailColumn The column that holds the email of the person a
     *                                 row is about. A row whose domain is allowed keeps
     *                                 its identity columns.
     */
    public function __construct(
        public bool $rows = true,
        public array $where = [],
        public array $columns = [],
        public ?string $seedColumn = null,
        public ?string $emailColumn = null,
    ) {}

    public static function structureOnly(): self
    {
        return new self(rows: false);
    }

    public function merge(self $other): self
    {
        return new self(
            rows: $this->rows && $other->rows,
            where: [...$this->where, ...$other->where],
            columns: [...$this->columns, ...$other->columns],
            seedColumn: $this->seedColumn ?? $other->seedColumn,
            emailColumn: $this->emailColumn ?? $other->emailColumn,
        );
    }

    /**
     * The conditions as one WHERE clause, or null when every row is kept.
     */
    public function whereClause(): ?string
    {
        if ($this->where === []) {
            return null;
        }

        return implode(' AND ', array_map(static fn(string $condition): string => "({$condition})", $this->where));
    }

    /**
     * How the export report names this treatment.
     */
    public function describe(): string
    {
        if (!$this->rows) {
            return 'structure only';
        }

        $parts = [];

        if ($this->where !== []) {
            $parts[] = 'filtered';
        }

        if ($this->columns !== []) {
            $parts[] = 'anonymized';
        }

        return $parts === [] ? 'all rows' : implode(', ', $parts);
    }
}
