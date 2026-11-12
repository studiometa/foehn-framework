<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DatabaseExport;

/**
 * The rules of one plugin, applied only when one of its tables exists.
 *
 * A preset can also hold rules for core tables — WooCommerce adds conditions on
 * `postmeta` — and those apply only when the plugin is there.
 */
final readonly class Preset
{
    /**
     * @param list<string> $markers Table names, without the prefix, whose presence
     *                              turns the preset on. A trailing `*` matches a prefix.
     * @param array<string, TableRule> $rules Rules keyed by table name, without the prefix.
     */
    public function __construct(
        public string $name,
        public array $markers,
        public array $rules,
    ) {}

    /**
     * @param list<string> $tables Real table names.
     */
    public function isActive(array $tables, string $prefix): bool
    {
        foreach ($tables as $table) {
            foreach ($this->markers as $marker) {
                if (ExportPlan::matches($marker, $table, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
