<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

use Studiometa\Foehn\Config\DatabaseExportConfig;

/**
 * The rule each table of one export gets.
 *
 * Fail-closed: a table that no core rule, active preset or project setting covers
 * is exported as structure only, and is listed in {@see ExportPlan::$uncovered} so
 * the project can decide what it holds.
 */
final readonly class ExportPlan
{
    /**
     * @param array<string, TableRule> $tables The rule of each table, by real name.
     * @param list<string> $uncovered Tables no rule covers, exported as structure only.
     * @param list<string> $presets Names of the presets that apply.
     */
    public function __construct(
        public array $tables,
        public array $uncovered,
        public array $presets,
    ) {}

    /**
     * @param list<string> $tables Real table names of the database.
     * @param string $from The start of the window, as `Y-m-d H:i:s` in UTC.
     */
    public static function resolve(array $tables, string $prefix, string $from, DatabaseExportConfig $config): self
    {
        $builtIn = new BuiltInRules($prefix, $from);
        $ruleSets = [$builtIn->core()];
        $presets = [];

        foreach ($builtIn->presets($tables) as $preset) {
            if (!$preset->isActive($tables, $prefix)) {
                continue;
            }

            $ruleSets[] = $preset->rules;
            $presets[] = $preset->name;
        }

        $ruleSets[] = array_fill_keys($config->safeTables, new TableRule());
        $ruleSets[] = array_map(
            static fn(array $columns): TableRule => new TableRule(columns: $columns),
            $config->columns,
        );
        $ruleSets[] = array_fill_keys($config->structureOnlyTables, TableRule::structureOnly());

        $rules = [];
        $uncovered = [];

        foreach ($tables as $table) {
            $rule = null;

            foreach ($ruleSets as $ruleSet) {
                foreach ($ruleSet as $pattern => $candidate) {
                    if (!self::matches($pattern, $table, $prefix)) {
                        continue;
                    }

                    $rule = $rule === null ? $candidate : $rule->merge($candidate);
                }
            }

            if ($rule === null) {
                $uncovered[] = $table;
                $rule = TableRule::structureOnly();
            }

            $rules[$table] = $rule;
        }

        return new self($rules, $uncovered, $presets);
    }

    /**
     * Whether a table name without its prefix — `posts`, or `actionscheduler_*` —
     * names this table.
     */
    public static function matches(string $pattern, string $table, string $prefix): bool
    {
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($table, $prefix . substr($pattern, 0, -1));
        }

        return $table === $prefix . $pattern;
    }

    /**
     * The tables exported without rows.
     *
     * @return list<string>
     */
    public function structureOnly(): array
    {
        return array_keys(array_filter($this->tables, static fn(TableRule $rule): bool => !$rule->rows));
    }

    /**
     * The WHERE clause of each filtered table.
     *
     * @return array<string, string>
     */
    public function wheres(): array
    {
        $wheres = [];

        foreach ($this->tables as $table => $rule) {
            $clause = $rule->whereClause();

            if ($rule->rows && $clause !== null) {
                $wheres[$table] = $clause;
            }
        }

        return $wheres;
    }
}
