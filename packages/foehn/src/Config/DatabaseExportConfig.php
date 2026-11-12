<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Studiometa\Foehn\DatabaseExport\Anonymizer;

/**
 * What `wp foehn db:export` does with the tables Føhn has no rule for.
 *
 * Table names are given without the table prefix. A trailing `*` matches every
 * table whose name starts with the rest.
 *
 * ```php
 * // app/db-export.config.php
 * use Studiometa\Foehn\Config\DatabaseExportConfig;
 * use Studiometa\Foehn\DatabaseExport\Anonymizer;
 *
 * return new DatabaseExportConfig(
 *     safeTables: ['store_locations'],
 *     structureOnlyTables: ['newsletter_subscribers'],
 *     columns: [
 *         'event_registrations' => [
 *             'email' => Anonymizer::Email,
 *             'phone' => Anonymizer::Empty,
 *         ],
 *     ],
 *     allowedEmailDomains: ['example.com'],
 * );
 * ```
 */
final readonly class DatabaseExportConfig
{
    public function __construct(
        /**
         * How far back revisions and orders go, as a relative date `strtotime()` reads.
         * `--from` replaces it for one export.
         */
        public string $window = '-2 months',

        /**
         * Tables exported with all their rows. They hold nothing personal.
         *
         * @var list<string>
         */
        public array $safeTables = [],

        /**
         * Tables exported without rows. This wins over every other rule, the built-in
         * ones included.
         *
         * @var list<string>
         */
        public array $structureOnlyTables = [],

        /**
         * Columns to replace, by table. The table is exported with its rows.
         *
         * @var array<string, array<string, Anonymizer>>
         */
        public array $columns = [],

        /**
         * Email domains whose people keep their real email, login and name — the
         * team's own accounts. Their password hash is still removed.
         *
         * @var list<string>
         */
        public array $allowedEmailDomains = [],
    ) {}

    /**
     * The start of the window, as `Y-m-d H:i:s` in UTC.
     *
     * @param string|null $from A date or a relative date. Null uses {@see self::$window}.
     * @throws InvalidArgumentException When the date cannot be read.
     */
    public function from(?string $from = null, ?DateTimeImmutable $now = null): string
    {
        $utc = new DateTimeZone('UTC');
        $value = $from ?? $this->window;
        $now = ($now ?? new DateTimeImmutable('now', $utc))->setTimezone($utc);

        try {
            // A date with a year is absolute; anything else is relative to now.
            $date = date_parse($value)['year'] !== false ? new DateTimeImmutable($value, $utc) : $now->modify($value);
        } catch (DateMalformedStringException) {
            throw new InvalidArgumentException(sprintf('"%s" is not a date.', $value));
        }

        return $date->setTimezone($utc)->format('Y-m-d H:i:s');
    }

    /**
     * Whether an email belongs to one of {@see self::$allowedEmailDomains}.
     */
    public function allowsEmail(?string $email): bool
    {
        $at = $email === null ? false : strrpos($email, '@');

        if ($email === null || $at === false) {
            return false;
        }

        $domain = strtolower(substr($email, $at + 1));

        return in_array($domain, array_map(strtolower(...), $this->allowedEmailDomains), true);
    }
}
