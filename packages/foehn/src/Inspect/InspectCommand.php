<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;

#[AsCliCommand(name: 'inspect', description: 'Report what the project declares', longDescription: <<<'DOC'
    ## DESCRIPTION

    Reports the project model: post types with their meta fields, taxonomies and
    Timber model classes, taxonomies, blocks, patterns, block bindings, template
    controllers, context providers, menus, image sizes, rewrite rules, REST routes,
    `wp foehn` commands, active plugins, theme supports and the Vite entry points.

    The report is built from what discovery found, so it reads the attributes the
    code declares. Run it before writing code in a project you do not know, and use
    `--format=json` when a program or a coding agent reads the result.

    With no section, it prints a summary: the names in each section, short enough
    to read whole. Name the sections to read them in full, or pass `--all` for the
    whole report. JSON is printed on one line.

    Inspecting registers nothing and writes nothing.

    ## OPTIONS

    [<section>...]
    : The sections to print in full. Any of post-types, taxonomies, blocks,
      patterns, bindings, templates, context-providers, menus, image-sizes,
      rewrite-rules, rest-routes, cli-commands, plugins, theme-supports, vite.

    [--all]
    : Print every section in full.

    [--format=<format>]
    : Output format. table (default) or json.

    ## EXAMPLES

        # What the project declares, by name
        wp foehn inspect

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
        'post-types' => ['name', 'model', 'taxonomies', 'meta'],
        'taxonomies' => ['name', 'model', 'postTypes', 'meta'],
        'blocks' => ['name', 'title', 'class'],
        'patterns' => ['name', 'title', 'template'],
        'bindings' => ['name', 'label', 'class'],
        'templates' => ['class', 'templates', 'priority'],
        'context-providers' => ['class', 'templates', 'priority'],
        'menus' => ['location', 'contextKey', 'class'],
        'image-sizes' => ['name', 'width', 'height', 'crop'],
        'rewrite-rules' => ['regex', 'query', 'class'],
        'rest-routes' => ['method', 'path', 'handler'],
        'cli-commands' => ['command', 'description'],
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
     * One section as a heading and padded rows.
     *
     * @param array<mixed> $data
     */
    private function renderSection(string $name, array $data): void
    {
        // The one section that is a record rather than a list: the dev server is a
        // fact about the build, and the rows are its entries.
        $isVite = $name === 'vite';
        $entries = $data['entries'] ?? [];
        $entries = is_array($entries) ? $entries : [];
        $rows = $isVite ? $entries : $data;

        $this->cli->line('');
        $this->cli->log(
            $isVite
                ? sprintf('vite — dev server: %s', self::scalar($data['devServer'] ?? null))
                : sprintf('%s — %d', $name, count($rows)),
        );

        if ($rows === []) {
            return;
        }

        $columns = self::TABLE_COLUMNS[$name];
        $lines = [$columns];

        foreach ($rows as $row) {
            $lines[] = array_map(static fn(string $column): string => self::cell(
                is_array($row) ? $row[$column] ?? null : null,
            ), $columns);
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
