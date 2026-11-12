<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;

#[AsCliCommand(name: 'inspect', description: 'Report what the project declares', longDescription: <<<'DOC'
    ## DESCRIPTION

    Reports the project model: the problems, post types with their meta fields,
    taxonomies and Timber model classes, taxonomies, settings pages with their
    options, blocks with their attributes, patterns, block bindings, templates,
    Twig views, context providers, menus, image sizes, rewrite rules, REST routes,
    WP-CLI commands (`wp app`, `wp foehn` and those of packages), seeders, active
    plugins, theme supports and the Vite entry points.

    `templates` gives the controller that renders each WordPress template, and a
    real URL to check it with: the newest published post of the type, the archive,
    a term with posts, the front page, a search or a missing page. A template that
    no such URL renders gives the reason instead. `views`
    gives, for each Twig file, the context providers its name matches. Both give
    the context keys: `base` keys are always set, the `keys` of a controller or a
    provider come from literal `with()` calls in its source and can depend on
    runtime conditions, and `partial` says that a class also sets keys only known
    at runtime. What every row shares is given once, above the rows: the base keys,
    the keys of each class, and in `views` the providers that match every view.

    The post meta keys, the options and the block attributes are where content is
    stored, so an agent can write it with core commands such as `wp post meta
    update` and `wp option update`. Sample content goes in a seeder instead, so
    `wp foehn seed` can write it again: `seeders` lists them in run order.

    `problems` lists what the project declares and WordPress will not register:
    a class discovery skipped because it breaks the contract of its attribute
    (#[AsPostType] on a class that does not extend Timber\Post, a command that
    does not implement its interface…), and a post type or taxonomy that names a
    taxonomy or post type nobody registers. Each row gives the reason and, when
    the cause is known, the fix. The summary names them first.

    The report is built from what discovery found, so it reads the attributes the
    code declares. Run it before writing code in a project you do not know, and use
    `--format=json` when a program or a coding agent reads the result.

    With no section, it prints a summary: the names in each section, short enough
    to read whole. Name the sections to read them in full, or pass `--all` for the
    whole report. JSON is printed on one line.

    Inspecting registers nothing and writes nothing.

    ## OPTIONS

    [<section>...]
    : The sections to print in full. Any of problems, post-types, taxonomies,
      settings, blocks, patterns, bindings, templates, views, context-providers,
      menus, image-sizes, rewrite-rules, rest-routes, cli-commands, seeders,
      plugins, theme-supports, vite.

    [--all]
    : Print every section in full.

    [--format=<format>]
    : Output format. table (default) or json.

    ## EXAMPLES

        # What the project declares, by name
        wp foehn inspect

        # What will not register, and how to fix it
        wp foehn inspect problems

        # Some sections in full, for a program or an agent
        wp foehn inspect post-types templates menus --format=json

        # Every section in full
        wp foehn inspect --all --format=json
    DOC)]
final readonly class InspectCommand implements CliCommandInterface
{
    /** @var list<string> */
    private const array FORMATS = ['table', 'json'];

    /**
     * The columns a table shows per section. JSON carries every field; a table that
     * did would be wider than any terminal.
     *
     * @var array<string, list<string>>
     */
    private const array TABLE_COLUMNS = [
        'problems' => ['reason', 'fix'],
        'post-types' => ['name', 'model', 'taxonomies', 'meta'],
        'taxonomies' => ['name', 'model', 'postTypes', 'meta'],
        'settings' => ['page', 'class', 'settings'],
        'blocks' => ['name', 'title', 'attributes'],
        'patterns' => ['name', 'title', 'template'],
        'bindings' => ['name', 'label', 'class'],
        'templates' => ['template', 'controller', 'url', 'request', 'keys'],
        'views' => ['view', 'providers', 'keys'],
        'context-providers' => ['class', 'templates', 'keys'],
        'menus' => ['location', 'contextKey', 'class'],
        'image-sizes' => ['name', 'width', 'height', 'crop'],
        'rewrite-rules' => ['regex', 'query', 'class'],
        'rest-routes' => ['method', 'path', 'handler'],
        'cli-commands' => ['command', 'description'],
        'seeders' => ['class', 'order', 'description'],
        'plugins' => ['name', 'version', 'file'],
        'theme-supports' => ['feature', 'value'],
        'vite' => ['entry', 'file', 'css'],
    ];

    public function __construct(
        private WpCli $cli,
        private ProjectInspector $inspector,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'table';

        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $this->cli->error(sprintf("Unknown format '%s'. Use table or json.", self::scalar($format)));

            return;
        }

        $sections = array_values(array_unique($args));
        $unknown = array_diff($sections, ProjectInspector::SECTIONS);

        if ($unknown !== []) {
            $this->cli->error(sprintf(
                "Unknown section '%s'. Available sections: %s.",
                implode("', '", $unknown),
                implode(', ', ProjectInspector::SECTIONS),
            ));

            return;
        }

        $all = ($assocArgs['all'] ?? null) !== null;

        if ($all && $sections !== []) {
            $this->cli->error('Name sections or pass --all, not both.');

            return;
        }

        if ($sections === [] && !$all) {
            $this->renderSummary($this->inspector->summary(), $format);

            return;
        }

        $report = $all
            ? $this->inspector->all()
            : array_combine($sections, array_map($this->inspector->section(...), $sections));

        if ($format === 'json') {
            $this->renderJson($report);

            return;
        }

        foreach ($report as $name => $data) {
            $this->renderSection($name, $data);
        }
    }

    /**
     * The names in each section, and the commands that print them in full.
     *
     * @param array<string, list<string>> $summary
     */
    private function renderSummary(array $summary, string $format): void
    {
        if ($format === 'json') {
            $this->renderJson([
                'summary' => $summary,
                'commands' => [
                    'problems' => 'wp foehn inspect problems --format=json',
                    'sections' => 'wp foehn inspect <section>... --format=json',
                    'all' => 'wp foehn inspect --all --format=json',
                ],
            ]);

            return;
        }

        foreach ($summary as $name => $names) {
            $this->cli->log(sprintf(
                '%s — %d%s',
                $name,
                count($names),
                $names === [] ? '' : ': ' . implode(', ', $names),
            ));
        }

        $this->cli->line('');

        if (($summary['problems'] ?? []) !== []) {
            $this->cli->log('Problems with their fix: wp foehn inspect problems');
        }

        $this->cli->log('Sections in full: wp foehn inspect <section>...');
        $this->cli->log('Every section in full: wp foehn inspect --all');
    }

    /**
     * JSON on one line: a program or an agent reads it, and indentation would
     * double its size.
     *
     * @param array<string, mixed> $data
     */
    private function renderJson(array $data): void
    {
        // Plugin headers and theme support arguments come from code Føhn does not
        // control. One value json_encode() cannot take must not turn the whole
        // report into an empty line: it is replaced, and the rest is printed.
        $this->cli->line((string) json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));
    }

    /**
     * One section as a heading, what its rows share, and padded rows.
     *
     * @param array<mixed> $data
     */
    private function renderSection(string $name, array $data): void
    {
        [$heading, $shared, $rows] = match ($name) {
            'vite' => self::viteTable($data),
            'templates' => self::templatesTable($data),
            'views' => self::viewsTable($data),
            default => [sprintf('%s — %d', $name, count($data)), [], array_values($data)],
        };

        $this->cli->line('');
        $this->cli->log($heading);

        if ($rows === []) {
            return;
        }

        foreach ($shared as $line) {
            $this->cli->log('  ' . $line);
        }

        $columns = self::TABLE_COLUMNS[$name];
        $lines = [$columns];

        foreach ($rows as $row) {
            $lines[] = array_map(static fn(string $column): string => self::cell(self::field($row, $column)), $columns);
        }

        $widths = [];

        foreach ($lines as $line) {
            foreach ($line as $index => $value) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($value));
            }
        }

        foreach ($lines as $line) {
            $cells = [];

            foreach ($line as $index => $value) {
                $cells[] = $value . str_repeat(' ', $widths[$index] - mb_strlen($value));
            }

            $this->cli->log('  ' . rtrim(implode('  ', $cells)));
        }
    }

    /**
     * The one section about the build: the dev server is a fact about it, and the
     * rows are its entries.
     *
     * @param array<mixed> $data
     * @return array{string, list<string>, list<mixed>}
     */
    private static function viteTable(array $data): array
    {
        return [
            sprintf('vite — dev server: %s', self::scalar($data['devServer'] ?? null)),
            [],
            self::listOf($data['entries'] ?? null),
        ];
    }

    /**
     * Templates, with the keys every controller receives above the rows, and the keys
     * of its controller on each row. A template with no test URL gives the reason in
     * its place.
     *
     * @param array<mixed> $data
     * @return array{string, list<string>, list<mixed>}
     */
    private static function templatesTable(array $data): array
    {
        $rows = self::listOf($data['templates'] ?? null);
        $controllers = $data['controllers'] ?? [];

        return [
            sprintf('templates — %d', count($rows)),
            ['every template: ' . self::cell($data['base'] ?? null)],
            array_map(static fn(mixed $row): array => [
                ...(is_array($row) ? $row : []),
                'url' => self::field($row, 'url') ?? 'none: ' . self::scalar(self::field($row, 'noUrl')),
                'keys' => self::field($controllers, self::scalar(self::field($row, 'controller')) . '.keys'),
            ], $rows),
        ];
    }

    /**
     * Views, with what every view receives above the rows: the base keys, and the
     * providers that match every view with their keys. Each row shows only the
     * providers and keys that view adds.
     *
     * @param array<mixed> $data
     * @return array{string, list<string>, list<mixed>}
     */
    private static function viewsTable(array $data): array
    {
        $rows = self::listOf($data['views'] ?? null);
        $providers = $data['providers'] ?? [];
        $keysOf = static fn(mixed $class): array => self::listOf(self::field(
            $providers,
            self::scalar($class) . '.keys',
        ));
        $shared = ['every view: ' . self::cell($data['base'] ?? null)];

        foreach (self::listOf($data['everyView'] ?? null) as $class) {
            $shared[] = sprintf('every view, from %s: %s', self::scalar($class), self::cell($keysOf($class)));
        }

        return [
            sprintf('views — %d', count($rows)),
            $shared,
            array_map(static fn(mixed $row): array => [
                ...(is_array($row) ? $row : []),
                'keys' => array_merge(...array_map($keysOf, self::listOf(self::field($row, 'providers')))),
            ], $rows),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * A field of a row, following a dotted column into nested records.
     */
    private static function field(mixed $row, string $column): mixed
    {
        foreach (explode('.', $column) as $segment) {
            if (!is_array($row)) {
                return null;
            }

            $row = $row[$segment] ?? null;
        }

        return $row;
    }

    /**
     * One value on one line.
     *
     * A list of records — the meta fields of a post type — shows the first field of
     * each, which is the one that names it. A nested list is flattened.
     */
    private static function cell(mixed $value): string
    {
        if (!is_array($value)) {
            return self::scalar($value);
        }

        $parts = array_map(static function (mixed $item): string {
            if (!is_array($item)) {
                return self::scalar($item);
            }

            return array_is_list($item) ? self::cell($item) : self::scalar(reset($item));
        }, $value);

        return implode(', ', $parts);
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) preg_replace('/\s+/', ' ', (string) $value),
            default => get_debug_type($value),
        };
    }
}
