<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

use Studiometa\Foehn\Config\DatabaseExportConfig;

/**
 * Replaces the columns a rule names in each row the dump writes.
 *
 * Given to `Mysqldump::setTransformTableRowHook()`, which calls it with the table
 * name and the row as PDO returned it.
 */
final readonly class RowTransformer
{
    /**
     * @param array<string, TableRule> $rules By real table name.
     */
    public function __construct(
        private array $rules,
        private DatabaseExportConfig $config,
    ) {}

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function __invoke(string $table, array $row): array
    {
        $rule = $this->rules[$table] ?? null;

        if ($rule === null || $rule->columns === []) {
            return $row;
        }

        $trusted =
            $rule->emailColumn !== null && $this->config->allowsEmail(self::string($row[$rule->emailColumn] ?? null));
        $seed = $rule->seedColumn !== null ? self::string($row[$rule->seedColumn] ?? null) : null;

        foreach ($rule->columns as $column => $anonymizer) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            if ($trusted && $anonymizer->isIdentity()) {
                continue;
            }

            $row[$column] = $anonymizer->apply(self::string($row[$column]), $seed);
        }

        return $row;
    }

    private static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
