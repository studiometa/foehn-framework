# Routes, commands and extensions

REST routes, rewrite rules, shortcodes, WP-CLI commands, background jobs, cron, Twig extensions and custom discoveries.

Docs: [REST API](https://studiometa.github.io/foehn-framework/guide/rest-api.html), [Rewrite rules](https://studiometa.github.io/foehn-framework/guide/rewrite-rules.html), [Shortcodes](https://studiometa.github.io/foehn-framework/guide/shortcodes.html), [CLI commands](https://studiometa.github.io/foehn-framework/guide/cli-commands.html), [Twig extensions](https://studiometa.github.io/foehn-framework/guide/twig-extensions.html), [Custom discovery](https://studiometa.github.io/foehn-framework/guide/custom-discovery.html).

## `#[AsRestRoute]`

On a method. Repeatable. The class is built by the container, so constructor injection works.

```php
namespace App\Rest;

use Studiometa\Foehn\Attributes\AsRestRoute;
use WP_REST_Request;
use WP_REST_Response;

final readonly class ProjectsApi
{
    #[AsRestRoute('theme/v1', '/projects', permission: 'public')]
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $projects = Project::query()->limit(10)->page((int) ($request->get_param('page') ?? 1))->get();

        return new WP_REST_Response(array_map(
            static fn(Project $project): array => ['id' => $project->ID, 'title' => $project->title()],
            $projects,
        ));
    }

    #[AsRestRoute(
        namespace: 'theme/v1',
        route: '/projects/(?P<id>\d+)',
        method: 'POST',
        permission: 'canEdit',
        args: ['id' => ['type' => 'integer', 'required' => true]],
    )]
    public function update(WP_REST_Request $request): WP_REST_Response
    {
        // …
        return new WP_REST_Response(['ok' => true]);
    }

    public function canEdit(WP_REST_Request $request): bool
    {
        return current_user_can('edit_post', (int) $request->get_param('id'));
    }
}
```

- `method`: one of `GET` (default), `POST`, `PUT`, `PATCH`, `DELETE`. Declare one attribute per method.
- `permission`:
  - `null` (default): `current_user_can(RestConfig::defaultCapability)`, which is `edit_posts`. With `new RestConfig(defaultCapability: null)` in `app/rest.config.php`, any logged-in user.
  - `'public'`: no check.
  - any other string: the name of a method on the same class, called with the request.
- `args`: the `register_rest_route()` argument schema.

## `#[AsRewriteRule]`

A URL the theme answers itself: a webhook, a form handler, a signed download.

```php
namespace App\Routes;

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use WP;

#[AsRewriteRule(regex: '^_health/?$', query: 'index.php?foehn_route=health', queryVars: ['foehn_route'])]
final readonly class HealthCheckRoute implements RewriteHandlerInterface
{
    public function handle(WP $wp): void
    {
        status_header(200);
        header('Content-Type: application/json');
        echo (string) json_encode(['status' => 'ok']);
        exit();
    }
}
```

- `handle()` runs on `parse_request`, before the main query. `exit` to answer. Return to let WordPress continue.
- Without `RewriteHandlerInterface`, the rule only rewrites to something WordPress renders: `#[AsRewriteRule(regex: '^brochure/([^/]+)/?$', query: 'index.php?post_type=brochure&name=$matches[1]', after: 'bottom')]`.
- `queryVars` registers the query vars. WordPress drops a var it does not know.
- `after`: `'top'` (default, before core rules) or `'bottom'`.
- Føhn flushes the rules when the set of declarations changes. `wp foehn rewrite:flush` is for rules something else made stale.

## `#[AsShortcode]`

On a method. The method receives `$atts` (always an array), `$content` and the tag, and returns the HTML.

```php
namespace App\Shortcodes;

use Studiometa\Foehn\Attributes\AsShortcode;

final class ButtonShortcode
{
    #[AsShortcode('button')]
    public function render(array $atts, ?string $content = null): string
    {
        $atts = shortcode_atts(['url' => '#', 'style' => 'primary'], $atts);

        return sprintf(
            '<a href="%s" class="btn btn--%s">%s</a>',
            esc_url($atts['url']),
            esc_attr($atts['style']),
            esc_html($content ?? ''),
        );
    }
}
```

WordPress passes `''` as `$atts` when the shortcode has no attributes. Føhn turns it into `[]` before it calls the method. Inject `ViewEngineInterface` to render a Twig template instead of building HTML in PHP.

## `#[AsCliCommand]`

On a class that implements `Studiometa\Foehn\Console\CliCommandInterface`. Without the interface, the command is not registered. The command is `wp foehn <name>`.

```php
namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;

#[AsCliCommand(
    name: 'import:projects',
    description: 'Import projects from a CSV file',
    longDescription: <<<'DOC'
        ## OPTIONS

        <file>
        : Path to the CSV file

        [--dry-run]
        : Show what would be imported, and import nothing

        ## EXAMPLES

            wp foehn import:projects projects.csv --dry-run
        DOC,
)]
final readonly class ImportProjectsCommand implements CliCommandInterface
{
    public function __construct(
        private WpCli $cli,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $args[0] ?? '';

        if (!is_file($file)) {
            $this->cli->error("File not found: {$file}");
        }

        $dryRun = isset($assocArgs['dry-run']);
        // …
        $this->cli->success('Import complete');
    }
}
```

- `longDescription` uses the WP-CLI docblock format. WP-CLI reads the `## OPTIONS` synopsis from it to validate arguments.
- `Studiometa\Foehn\Console\WpCli` wraps `WP_CLI`: `log`, `line`, `success`, `warning`, `error`, `halt`, `confirm`, `prompt`, `table`, `colorize`, `withSpinner`. You can also call `WP_CLI` directly.

## Background jobs: `#[AsJob]`

Jobs run through Action Scheduler. Install `woocommerce/action-scheduler`, or `dispatch()` throws.

```php
namespace App\Jobs;

use Studiometa\Foehn\Attributes\AsJob;

// The payload: a plain DTO. It is serialized, so keep it to scalars and arrays.
final readonly class ImportProject
{
    public function __construct(public int $importId, public string $source) {}
}

// The handler: one public __invoke() with exactly one typed parameter.
#[AsJob] // group: 'foehn', hook: derived from the DTO class
final readonly class ImportProjectHandler
{
    public function __invoke(ImportProject $job): void
    {
        // …
    }
}
```

```php
use function Studiometa\Foehn\dispatch;

dispatch(new ImportProject(42, 'csv'));             // as soon as possible
dispatch(new ImportProject(42, 'csv'), delay: 300); // in five minutes
```

You can also inject `Studiometa\Foehn\Contracts\JobDispatcher` and call `dispatch()` on it.

## Recurring jobs: `#[AsCron]`

```php
use Studiometa\Foehn\Attributes\AsCron;
use Studiometa\Foehn\Jobs\CronInterval;

#[AsCron(CronInterval::Daily)] // Hourly, TwiceDaily, Daily, Weekly, or seconds: #[AsCron(300)]
final readonly class PurgeExpiredExports
{
    public function __invoke(): void
    {
        // …
    }
}
```

The class needs a public `__invoke()` with no required arguments. Føhn schedules it with Action Scheduler when it is not scheduled yet. Without Action Scheduler, nothing is scheduled.

## `#[AsTwigExtension]`

On a class that extends `Twig\Extension\AbstractExtension`. Constructor injection works.

```php
namespace App\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[AsTwigExtension] // priority: 10
final class ThemeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('reading_time', $this->readingTime(...))];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('initials', static fn(string $name): string => implode('', array_map(
            static fn(string $part): string => mb_substr($part, 0, 1),
            explode(' ', $name),
        )))];
    }

    public function readingTime(string $content): int
    {
        return max(1, (int) ceil(str_word_count(wp_strip_all_tags($content)) / 200));
    }
}
```

## Custom discoveries

For a WordPress feature Føhn does not cover, write your own attribute and discovery. A class that implements `Tempest\Discovery\Discovery` in a scanned location is found automatically.

```php
namespace App\Discovery;

use App\Attributes\AsWidget;
use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

#[AsDiscovery(phase: DiscoveryPhase::Early)] // Early: after_setup_theme, Main (default): init, Late: wp_loaded
final class WidgetDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsWidget::class);

        if ($attribute === null) {
            return;
        }

        $this->addItem($location, ['attribute' => $attribute, 'className' => $class->getName()]);
    }

    public function apply(): void
    {
        add_action('widgets_init', function (): void {
            foreach ($this->getItems() as $item) {
                register_widget($item['className']);
            }
        });
    }
}
```

- Store in an item only the attribute and reflection facts (class name, method name). Items go into the cache through `var_export()`: no closures, no objects other than the attribute. Compute derived values in `apply()`.
- Make the attribute a `final readonly class` with `#[Attribute]` and constructor property promotion.
