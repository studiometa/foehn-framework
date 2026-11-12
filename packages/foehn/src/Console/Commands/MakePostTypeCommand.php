<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Commands;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\GenerationRequest;
use Studiometa\Foehn\Console\ProjectConventions;
use Studiometa\Foehn\Console\Scaffolder;
use Studiometa\Foehn\Console\Stubs\PostTypeStub;
use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;

use function Tempest\Support\str;

#[AsCliCommand(name: 'make:post-type', description: 'Create a new custom post type class', longDescription: <<<'DOC'
    ## DESCRIPTION

    Writes a model class that registers the post type with #[AsPostType], and one
    #[AsPostMeta] with an accessor for each meta field.

    The class goes where the project keeps its post types, in their namespace and
    under their parent class. A project with none gets app/Models/ and
    Studiometa\Foehn\Models\Post. The next steps name the Twig files the project's
    template controllers render for the single page and the archive.

    ## OPTIONS

    <name>
    : The post type slug (e.g., 'project', 'team-member')

    [--class=<class>]
    : Custom class name (defaults to PascalCase of name)

    [--singular=<singular>]
    : Singular label (defaults to humanized name)

    [--plural=<plural>]
    : Plural label (defaults to singular + 's')

    [--rewrite-slug=<slug>]
    : The URL slug of the single pages and the archive (defaults to the name)

    [--meta=<fields>]
    : Meta fields, comma-separated, each `key` or `key:type`. The type is string
      (default), integer, number or boolean. Store a date as a string in Y-m-d, or
      Y-m-d H:i:s, so a meta_query can compare it with type DATE or DATETIME.

    [--force]
    : Overwrite existing file

    [--dry-run]
    : Write nothing. Show the file that would be written, or report that it exists

    [--format=<format>]
    : Output format. text (default) or json. json prints the written files, their status and the next steps

    ## EXAMPLES

        # Create a simple post type
        wp foehn make:post-type project

        # Create with custom labels
        wp foehn make:post-type team-member --singular="Team Member" --plural="Team Members"

        # Create with meta fields and a plural URL
        wp foehn make:post-type event --rewrite-slug=events --meta=start_date,venue,ticket_url,capacity:integer

        # Preview what would be created, as a coding agent reads it
        wp foehn make:post-type project --dry-run --format=json
    DOC)]
final class MakePostTypeCommand implements CliCommandInterface
{
    /**
     * The meta types an accessor is generated for, with its return type and the
     * expression that reads `$value`. An array or an object needs a REST schema,
     * which only the author can write.
     */
    private const array ACCESSORS = [
        'string' => ['?string', "is_string(\$value) && \$value !== '' ? \$value : null"],
        'integer' => ['?int', 'is_numeric($value) ? (int) $value : null'],
        'number' => ['?float', 'is_numeric($value) ? (float) $value : null'],
        'boolean' => ['bool', '(bool) $value'],
    ];

    public function __construct(
        private readonly WpCli $cli,
        private readonly Scaffolder $scaffolder,
        private readonly ProjectConventions $conventions,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->cli->error('Please provide a post type name.');

            return;
        }

        $meta = $assocArgs['meta'] ?? '';
        $rewriteSlug = $assocArgs['rewrite-slug'] ?? null;

        // WP-CLI gives `true` for an option written without `=<value>`.
        if (!is_string($meta) || $rewriteSlug === true || $rewriteSlug === '') {
            $this->cli->error(
                'Give --meta and --rewrite-slug a value, e.g. --meta=start_date,venue --rewrite-slug=events.',
            );

            return;
        }

        $fields = self::parseMeta($meta);

        if (is_string($fields)) {
            $this->cli->error($fields);

            return;
        }

        $className = $assocArgs['class'] ?? str($name)->pascal()->toString();
        $singular = $assocArgs['singular'] ?? str($name)->replace(['-', '_'], ' ')->title()->toString();
        $plural = $assocArgs['plural'] ?? $singular . 's';
        $attributeArguments = ['name' => $name, 'singular' => $singular, 'plural' => $plural];

        if (is_string($rewriteSlug)) {
            $attributeArguments['rewriteSlug'] = $rewriteSlug;
        }

        $request = static fn(array $methods): GenerationRequest => new GenerationRequest(
            stub: PostTypeStub::class,
            subdirectory: 'Models',
            className: $className,
            attributeArguments: $attributeArguments,
            discovery: PostTypeDiscovery::class,
            attributes: array_map(static fn(array $field): array => [
                'name' => AsPostMeta::class,
                'arguments' => ['key' => $field['key'], 'type' => $field['type']],
            ], $fields),
            methods: $methods,
        );

        $notes = [
            $this->conventions->view('single', $name)->note(),
            $this->conventions->view('archive', $name)->note(),
        ];
        $methods = [];
        $accessorOf = [];
        $parent = $this->conventions->place($request([]))->parent ?? (string) get_parent_class(PostTypeStub::class);

        foreach ($fields as $field) {
            $method = str($field['key'])->camel()->toString();

            // A method the parent has would replace it for every caller, Timber's
            // own templates included: date() or link() under another meaning breaks them.
            $conflict = match (true) {
                preg_match('/^[a-z_][a-zA-Z0-9_]*$/', $method) !== 1 => 'is not a valid method name',
                method_exists($parent, $method) => "would replace {$parent}::{$method}()",
                array_key_exists(strtolower($method), $accessorOf) => sprintf(
                    "is already the accessor of '%s'",
                    $accessorOf[strtolower($method)],
                ),
                default => null,
            };

            if ($conflict !== null) {
                $notes[] = sprintf(
                    "No accessor for '%s': %s() %s. Read it with post.meta('%s'), or add an accessor under another name.",
                    $field['key'],
                    $method,
                    $conflict,
                    $field['key'],
                );

                continue;
            }

            [$returnType, $expression] = self::ACCESSORS[$field['type']];
            // PHP method names ignore case: startDate() and startdate() are one method.
            $accessorOf[strtolower($method)] = $field['key'];
            $methods[$method] = [
                'returnType' => $returnType,
                'body' => "\$value = \$this->meta('{$field['key']}');\n\nreturn {$expression};",
            ];
        }

        if ($fields !== []) {
            $notes[] =
                'WordPress gives editors no form for these fields: add custom-fields to supports for the Custom Fields panel, '
                . 'bind them to blocks with the core/post-meta source, or manage them with ACF. '
                . 'Write them with `wp post meta update <id> <key> <value>`.';
        }

        $notes[] = 'Run `wp foehn rewrite:flush` once the class is in place, so the new URLs resolve.';

        $this->scaffolder->scaffold($request($methods), $assocArgs, 'Post type created', $notes);
    }

    /**
     * Read `--meta`: `key` or `key:type`, comma-separated.
     *
     * @return list<array{key: string, type: string}>|string The fields, or what is wrong with them
     */
    private static function parseMeta(string $option): array|string
    {
        $fields = [];

        foreach (array_filter(array_map('trim', explode(',', $option))) as $entry) {
            [$key, $type] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, 'string');

            if (preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
                return sprintf("Invalid meta key '%s'. Use letters, digits, '_' and '-'.", $key);
            }

            if (!array_key_exists($type, self::ACCESSORS)) {
                return sprintf(
                    "Invalid type '%s' for meta key '%s'. Use %s. An array or an object needs a REST schema: declare it with #[AsPostMeta] by hand.",
                    $type,
                    $key,
                    implode(', ', array_keys(self::ACCESSORS)),
                );
            }

            if (in_array($key, array_column($fields, 'key'), true)) {
                return sprintf("Meta key '%s' is given twice.", $key);
            }

            $fields[] = ['key' => $key, 'type' => $type];
        }

        return $fields;
    }
}
