<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use ReflectionClass;
use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use UnitEnum;

#[AsCliCommand(name: 'discovery:list', description: 'List what discovery found', longDescription: <<<'DOC'
    ## DESCRIPTION

    Lists every discovery, the items it found, and where each item came from.

    Nothing else reports this: `discovery:status` answers how warm the cache is,
    not what registered. An empty discovery is listed rather than hidden, because
    "PostTypeDiscovery - 0 items" is the answer to most "why is my post type
    missing" questions.

    Each location is marked scanned or cached. A location restored from a cache
    written before your class existed reports zero items and no error.

    Listing registers nothing.

    ## OPTIONS

    [--discovery=<name>]
    : Only this discovery, by class name or short name. `--discovery=Hook`,
      `--discovery=HookDiscovery` and the fully qualified name all match.

    [--location=<namespace>]
    : Only items found in locations under this namespace, e.g. `--location=App`.

    [--format=<format>]
    : Output format. table (default), json, or count.

    ## EXAMPLES

        # Everything
        wp foehn discovery:list

        # One discovery
        wp foehn discovery:list --discovery=Hook

        # Only what the theme declares
        wp foehn discovery:list --location=App

        # Item counts alone, for a project with thousands
        wp foehn discovery:list --format=count
    DOC)]
final class DiscoveryListCommand implements CliCommandInterface
{
    /** How much of one argument value is worth printing before it stops being readable. */
    private const int MAX_VALUE_LENGTH = 48;

    public function __construct(
        private readonly WpCli $cli,
        private readonly DiscoveryRunner $runner,
        private readonly DiscoveryLocations $locations,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'table';

        if (!in_array($format, ['table', 'json', 'count'], true)) {
            $this->cli->error("Unknown format '{$format}'. Use table, json or count.");

            return;
        }

        $locationFilter = $assocArgs['location'] ?? null;
        $locations = $this->filterLocations($locationFilter);

        if ($locations === []) {
            $this->cli->error(
                $locationFilter === null
                    ? 'Nothing is being scanned. Foehn has no discovery location at all.'
                    : "No discovery location matches '{$locationFilter}'.",
            );

            return;
        }

        $discoveryFilter = $assocArgs['discovery'] ?? null;
        $discoveries = $this->filterDiscoveries($discoveryFilter);

        if ($discoveries === []) {
            $this->cli->error(
                $discoveryFilter === null
                    ? 'No discovery was found. Nothing scanned implements Tempest\\Discovery\\Discovery.'
                    : "No discovery matches '{$discoveryFilter}'.",
            );

            return;
        }

        $report = $this->report($discoveries, $locations);

        match ($format) {
            'json' => $this->renderJson($report),
            'count' => $this->renderCounts($report),
            default => $this->renderTable($report),
        };
    }

    /**
     * The report both renderers read, so the two cannot drift apart.
     *
     * @param array<class-string<Discovery>, Discovery> $discoveries
     * @param list<DiscoveryLocation> $locations
     * @return array{
     *     locations: list<array{namespace: string, path: string, origin: string}>,
     *     discoveries: list<array{
     *         class: string,
     *         name: string,
     *         phase: string,
     *         count: int,
     *         items: list<array{location: string, attribute: ?string, values: array<string, string>}>,
     *     }>,
     * }
     */
    private function report(array $discoveries, array $locations): array
    {
        $report = ['locations' => [], 'discoveries' => []];

        foreach ($locations as $location) {
            $report['locations'][] = [
                'namespace' => $location->namespace,
                'path' => $location->path,
                'origin' => $this->runner->wasRestoredFromCache($location) ? 'cached' : 'scanned',
            ];
        }

        foreach ($discoveries as $discoveryClass => $discovery) {
            $items = [];

            foreach ($locations as $location) {
                foreach ($discovery->getItems()->getForLocation($location) as $item) {
                    $items[] = $this->describeItem($location, $item);
                }
            }

            $report['discoveries'][] = [
                'class' => $discoveryClass,
                'name' => self::shortName($discoveryClass),
                'phase' => DiscoveryRunner::phaseOf($discoveryClass)->value,
                'count' => count($items),
                'items' => $items,
            ];
        }

        return $report;
    }

    /**
     * Describe one discovered item without knowing which discovery produced it.
     *
     * An item is an attribute instance plus the reflection facts that are not in
     * the attribute. Reading it back generically is what keeps this command from
     * growing a branch per discovery — a third-party one renders for free.
     *
     * @param mixed $item
     * @return array{location: string, attribute: ?string, values: array<string, string>}
     */
    private function describeItem(DiscoveryLocation $location, mixed $item): array
    {
        if (!is_array($item)) {
            return [
                'location' => $location->namespace,
                'attribute' => null,
                'values' => ['' => self::stringify($item)],
            ];
        }

        $values = [];
        $attribute = null;

        foreach ($item as $key => $value) {
            if ($key === 'attribute' && is_object($value)) {
                $attribute = $this->describeAttribute($value);

                continue;
            }

            $values[(string) $key] = self::stringify($value);
        }

        return ['location' => $location->namespace, 'attribute' => $attribute, 'values' => $values];
    }

    /**
     * An attribute as its short name and the arguments that were written out.
     *
     * The promoted constructor is the source: every Foehn attribute promotes all
     * of its parameters, so reflecting them gives the arguments in declaration
     * order. An argument still holding its default is left out — printing all
     * eighteen of #[AsBlock]'s buries the two that were set.
     */
    private function describeAttribute(object $attribute): string
    {
        $reflection = new ReflectionClass($attribute);
        $arguments = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();

            if (!$reflection->hasProperty($name)) {
                continue;
            }

            $property = $reflection->getProperty($name);

            if (!$property->isInitialized($attribute)) {
                continue;
            }

            $value = $property->getValue($attribute);

            if ($parameter->isDefaultValueAvailable() && $value === $parameter->getDefaultValue()) {
                continue;
            }

            $arguments[] = $name . ': ' . self::stringify($value);
        }

        return self::shortName($reflection->getName()) . '(' . implode(', ', $arguments) . ')';
    }

    /**
     * One value, on one line, short enough to sit in a column.
     *
     * Nothing is quoted. This is a listing meant to be read, and #[AsCliCommand]'s
     * long description alone is forty lines of heredoc.
     */
    private static function stringify(mixed $value): string
    {
        $string = match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            $value instanceof UnitEnum => self::shortName($value::class) . '::' . $value->name,
            is_array($value) => '[' . implode(', ', array_map(self::stringify(...), $value)) . ']',
            is_object($value) => self::shortName($value::class),
            default => get_debug_type($value),
        };

        return self::shorten((string) preg_replace('/\s+/', ' ', $string));
    }

    /**
     * A value cut to one column's worth.
     *
     * A class name is cut from the left. `Studiometa\Foehn\Console\Commands\Make…`
     * names nothing; `…\Commands\MakePostTypeCommand` names the class.
     */
    private static function shorten(string $value): string
    {
        if (mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
            return $value;
        }

        if (str_contains($value, '\\')) {
            return '…' . mb_substr($value, -(self::MAX_VALUE_LENGTH - 1));
        }

        return mb_substr($value, 0, self::MAX_VALUE_LENGTH - 1) . '…';
    }

    /**
     * The locations to report on, filtered by namespace.
     *
     * @return list<DiscoveryLocation>
     */
    private function filterLocations(?string $filter): array
    {
        $locations = $this->locations->all();

        if ($filter === null) {
            return $locations;
        }

        $needle = strtolower(trim($filter, '\\'));

        return array_values(array_filter($locations, static fn(DiscoveryLocation $location): bool => str_starts_with(
            strtolower(trim($location->namespace, '\\')),
            $needle,
        )));
    }

    /**
     * The discoveries to report on, filtered by name.
     *
     * `Hook`, `HookDiscovery` and the fully qualified name all name the same one:
     * the short form is what someone reading an error message has to hand.
     *
     * @return array<class-string<Discovery>, Discovery>
     */
    private function filterDiscoveries(?string $filter): array
    {
        $discoveries = $this->runner->getDiscoveries();

        if ($filter === null) {
            return $discoveries;
        }

        $needle = self::normalizeName($filter);

        return array_filter(
            $discoveries,
            static fn(Discovery $discovery): bool => self::normalizeName($discovery::class) === $needle,
        );
    }

    /**
     * A discovery name reduced to what makes it distinct.
     */
    private static function normalizeName(string $name): string
    {
        $short = self::shortName(trim($name, '\\'));

        return strtolower(preg_replace('/Discovery$/', '', $short) ?? $short);
    }

    private static function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * @param array{locations: list<array<string, string>>, discoveries: list<array<string, mixed>>} $report
     */
    private function renderJson(array $report): void
    {
        $this->cli->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array{locations: list<array<string, string>>, discoveries: list<array<string, mixed>>} $report
     */
    private function renderCounts(array $report): void
    {
        foreach ($report['discoveries'] as $discovery) {
            $this->cli->log(sprintf('%-32s %d', $discovery['name'], $discovery['count']));
        }

        $this->summarize($report);
    }

    /**
     * @param array{locations: list<array<string, string>>, discoveries: list<array<string, mixed>>} $report
     */
    private function renderTable(array $report): void
    {
        foreach ($report['discoveries'] as $discovery) {
            $this->cli->line('');
            $this->cli->log(sprintf(
                '%s (%s) — %d %s',
                $discovery['name'],
                $discovery['phase'],
                $discovery['count'],
                $discovery['count'] === 1 ? 'item' : 'items',
            ));

            foreach ($this->rows($discovery['items']) as $row) {
                $this->cli->log('  ' . $row);
            }
        }

        $this->cli->line('');
        $this->summarize($report);
    }

    /**
     * A header naming the item's fields, then one padded line per item.
     *
     * The header is what makes a bare `false` in the third column readable: item
     * shapes differ per discovery, and only the discovery that produced one knows
     * that its third field is `implementsConfig`.
     *
     * @param list<array{location: string, attribute: ?string, values: array<string, string>}> $items
     * @return list<string>
     */
    private function rows(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $lines = [['location', ...array_keys($items[0]['values'])]];

        foreach ($items as $item) {
            $lines[] = [$item['location'], ...array_values($item['values'])];
        }

        $widths = [];

        foreach ($lines as $line) {
            foreach ($line as $column => $value) {
                $widths[$column] = max($widths[$column] ?? 0, mb_strlen($value));
            }
        }

        $rows = [];

        foreach ($lines as $index => $line) {
            $cells = [];

            foreach ($line as $column => $value) {
                $cells[] = $value . str_repeat(' ', $widths[$column] - mb_strlen($value));
            }

            // The header's last cell names the attribute column; every other line
            // carries the attribute itself, when the discovery recorded one.
            $attribute = $index === 0 ? 'attribute' : $items[$index - 1]['attribute'];

            if ($attribute !== null) {
                $cells[] = $attribute;
            }

            $rows[] = rtrim(implode('  ', $cells));
        }

        return $rows;
    }

    /**
     * @param array{locations: list<array<string, string>>, discoveries: list<array<string, mixed>>} $report
     */
    private function summarize(array $report): void
    {
        $withItems = count(array_filter($report['discoveries'], static fn(array $d): bool => $d['count'] > 0));

        $this->cli->log(sprintf(
            '%d %s with items, %d empty.',
            $withItems,
            $withItems === 1 ? 'discovery' : 'discoveries',
            count($report['discoveries']) - $withItems,
        ));

        // Which locations were read back rather than walked. A discovery that finds
        // nothing in a cached location is not a discovery that found nothing.
        $this->cli->log(
            'Locations: '
                . implode(', ', array_map(
                    static fn(array $location): string => "{$location['namespace']} ({$location['origin']})",
                    $report['locations'],
                )),
        );
    }
}
