# Helpers

Global helper functions provided by Føhn.

## app()

Get the kernel instance or a service from the container.

```php
use function Studiometa\Foehn\app;

// Get the kernel
$kernel = app();

// Get a service
$viewEngine = app(ViewEngineInterface::class);
$myService = app(MyService::class);
```

### Signature

```php
/**
 * @template T of object
 * @param class-string<T>|null $class
 * @return ($class is null ? Kernel : T)
 */
function app(?string $class = null): object
```

## config()

Get a top-level key of the legacy array that the theme gave to `Kernel::boot()`. There is no dot notation: `config('a.b')` looks for the key `'a.b'`.

```php
use function Studiometa\Foehn\config;

// Kernel::boot(__DIR__ . '/app', ['debug' => true]);
$debug = config('debug', false);
```

A theme that boots with `Kernel::boot(__DIR__ . '/app')` and a `foehn.config.php` has no array, so `config()` always gives the default. To read settings, inject the config class, for example `FoehnConfig` or `TimberConfig`, or use `app(FoehnConfig::class)`. See [Configuration](/guide/configuration).

### Signature

```php
function config(string $key, mixed $default = null): mixed
```

## dispatch()

Queue a job. Action Scheduler stores it and runs it later, on a cron pass. The job is an object that holds data only. A class with `#[AsJob]` handles it: its `__invoke()` takes one parameter, typed with the class of the job.

```php
use Studiometa\Foehn\Attributes\AsJob;

use function Studiometa\Foehn\dispatch;

final readonly class ProcessImport
{
    public function __construct(
        public int $importId,
    ) {}
}

#[AsJob(group: 'imports')]
final class ProcessImportHandler
{
    public function __invoke(ProcessImport $job): void
    {
        // The work, in the background
    }
}

dispatch(new ProcessImport(42));

// Not before 5 minutes from now
dispatch(new ProcessImport(42), delay: 300);
```

Action Scheduler stores the public properties of the job. They must be scalar values or arrays: give an ID, not a post or a service. `dispatch()` throws a `RuntimeException` when Action Scheduler is not active, or when no `#[AsJob]` class handles the job. See [Cron Jobs](/guide/cron) for the jobs that run on a schedule.

### Signature

```php
function dispatch(object $job, ?int $delay = null): void
```

## Usage Examples

### Getting Services

```php
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use function Studiometa\Foehn\app;

// In a template or function
$view = app(ViewEngineInterface::class);
$html = $view->render('partials/card', ['title' => 'Hello']);
```

### Getting Kernel Properties

```php
use function Studiometa\Foehn\app;

$kernel = app();
$appPath = $kernel->getAppPath();
$isBooted = $kernel->isBooted();
```

### Configuration Access

```php
use Studiometa\Foehn\Config\FoehnConfig;

use function Studiometa\Foehn\app;

$config = app(FoehnConfig::class);
$debug = $config->isDebugEnabled();
```

## Namespace

The helpers are in the `Studiometa\Foehn` namespace:

```php
// Full namespace
\Studiometa\Foehn\app();
\Studiometa\Foehn\config('key');

// Or import
use function Studiometa\Foehn\app;
use function Studiometa\Foehn\config;
```

## WP

Helper class for typed access to WordPress global variables. Centralizes "unsafe" `$GLOBALS` access in a single, auditable location.

### db()

Get the WordPress database instance.

```php
use Studiometa\Foehn\Helpers\WP;

$results = WP::db()->get_results('SELECT * FROM ' . WP::db()->posts . ' LIMIT 10');
$prefix = WP::db()->prefix;
```

### query()

Get the main WordPress query.

```php
use Studiometa\Foehn\Helpers\WP;

$query = WP::query();
if ($query->is_main_query()) {
    // ...
}
```

### post()

Get the current post (or null if not set).

```php
use Studiometa\Foehn\Helpers\WP;

$post = WP::post();
if ($post !== null) {
    echo $post->post_title;
}
```

### user()

Get the current user (or null if not logged in).

```php
use Studiometa\Foehn\Helpers\WP;

$user = WP::user();
if ($user !== null) {
    echo "Hello, {$user->display_name}";
}
```

### withPost()

Run a callback with the global `$post` set to a given post, then put back the previous value. Some WordPress functions, such as `get_adjacent_post()`, read the global and do not take a post.

```php
use Studiometa\Foehn\Helpers\WP;

$next = WP::withPost($post, static fn() => get_adjacent_post(false, '', false));
```

### Why Use This?

Using `$GLOBALS` directly triggers static analysis warnings (e.g., Mago's `no-global` rule). This helper:

- Provides typed return values for better IDE support
- Centralizes unsafe access in one auditable location
- Makes code easier to test (can mock the helper)
- Follows Tempest's helper class patterns

## Env

The one place the framework decides which environment it is running in. Everything that behaves differently outside production — page-cache eligibility, the non-production indexing guard, production verification — reads it here, so a site cannot be read as two different environments by two features.

### get()

The current environment name, resolved the way WordPress resolves it:

1. `wp_get_environment_type()`, when WordPress is loaded;
2. the `WP_ENVIRONMENT_TYPE` constant, for readers that run before it is;
3. the `WP_ENVIRONMENT_TYPE` environment variable;
4. the `WP_ENV` environment variable, an accepted alias for it;
5. `production`.

```php
use Studiometa\Foehn\Helpers\Env;

$env = Env::get(); // 'production' | 'staging' | 'development' | 'local'
```

Steps 2 to 4 exist for the page-cache drop-in, which runs from `wp-settings.php` before `wp-includes/load.php` has defined the function. WordPress is preferred over the raw value because `wp_get_environment_type()` applies core's allowlist and its `WP_ENVIRONMENT_TYPE` filter — reading the variable first would silently ignore both.

**No `.env` file is read at runtime.** A production container injects environment variables without ever writing one, so a framework that needed the file would be reading nothing precisely where being right matters most. `WP_ENVIRONMENT_TYPE` reaches PHP through the generated `wp-config.php`, which is what loads `.env` when there is one.

`WP_ENV` is an alias, honoured by the generated `wp-config.php` as well as here: it resolves the environment once from either name and defines `WP_ENVIRONMENT_TYPE` from the result, so `wp_get_environment_type()`, the per-environment config file and `Env` cannot disagree. Step 4 above only ever answers for a reader that arrives before that constant exists — the page-cache drop-in.

::: warning `APP_ENV` is gone
Earlier releases resolved the environment from `APP_ENV` first. It is no longer read, with no fallback: it was never a WordPress name and nothing the framework generates has written it. Use `WP_ENVIRONMENT_TYPE`, or `WP_ENV` if a project already sets it.
:::

### is()

Check if the current environment matches.

```php
use Studiometa\Foehn\Helpers\Env;

if (Env::is('staging')) {
    // Enable staging features
}
```

### isProduction()

Check if running in production.

```php
use Studiometa\Foehn\Helpers\Env;

if (Env::isProduction()) {
    // Enable caching, disable debug output
}
```

### isDevelopment()

Check if running in development.

```php
use Studiometa\Foehn\Helpers\Env;

if (Env::isDevelopment()) {
    // Show debug toolbar
}
```

### isStaging()

Check if running in staging.

```php
use Studiometa\Foehn\Helpers\Env;

if (Env::isStaging()) {
    // Enable staging banner
}
```

### isLocal()

Check if running on a developer's own machine — exactly `local`, and not `development` as well. WordPress defines the two as separate types: `local` is a laptop, `development` is a shared server somebody develops against.

```php
use Studiometa\Foehn\Helpers\Env;

if (Env::isLocal()) {
    // Skip external API calls
}
```

### isDebug()

Check if WordPress debug mode is enabled.

```php
use Studiometa\Foehn\Helpers\Env;

if (Env::isDebug()) {
    // Show detailed errors
}
```

### Usage in Context Providers

```php
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Helpers\Env;
use Studiometa\Foehn\Views\TemplateContext;

#[AsContextProvider(templates: ['*'])]
final class GlobalContext implements ContextProviderInterface
{
    public function provide(TemplateContext $context): TemplateContext
    {
        return $context
            ->with('is_production', Env::isProduction())
            ->with('is_debug', Env::isDebug())
            ->with('environment', Env::get());
    }
}
```

Then in Twig:

```twig
{% verbatim %}{% if is_debug %}
    {{ dump(post) }}
{% endif %}

{% if not is_production %}
    <div class="env-banner">Environment: {{ environment }}</div>
{% endif %}{% endverbatim %}
```

## VideoEmbed

Helper class to transform video URLs to privacy-friendly embed URLs. Supports YouTube and Vimeo.

### embedUrl()

Convert a video URL to a privacy-friendly embed URL.

```php
use Studiometa\Foehn\Helpers\VideoEmbed;

// Basic usage
$embedUrl = VideoEmbed::embedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
// → https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ

// With options
$embedUrl = VideoEmbed::embedUrl('https://vimeo.com/123456789', [
    'autoplay' => true,
    'loop' => true,
    'muted' => true,  // Defaults to autoplay value
    'nocookie' => true,  // YouTube only, default true
]);
```

#### Supported URL Formats

**YouTube:**

- `youtube.com/watch?v=VIDEO_ID`
- `youtu.be/VIDEO_ID`
- `youtube.com/embed/VIDEO_ID`
- `youtube.com/v/VIDEO_ID`
- `youtube-nocookie.com/embed/VIDEO_ID`

**Vimeo:**

- `vimeo.com/VIDEO_ID`
- `vimeo.com/channels/CHANNEL/VIDEO_ID`
- `player.vimeo.com/video/VIDEO_ID`
- `vimeo.com/groups/GROUP/videos/VIDEO_ID`

#### YouTube Timestamps

Timestamps are automatically converted to the embed format:

```php
VideoEmbed::embedUrl('https://youtube.com/watch?v=dQw4w9WgXcQ&t=120');
// → https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=120

VideoEmbed::embedUrl('https://youtube.com/watch?v=dQw4w9WgXcQ&t=2m30s');
// → https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=150
```

### extractId()

Extract the video ID from a URL.

```php
VideoEmbed::extractId('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
// → 'dQw4w9WgXcQ'

VideoEmbed::extractId('https://vimeo.com/123456789');
// → '123456789'

VideoEmbed::extractId('https://example.com/video');
// → null
```

### detectPlatform()

Detect the video platform from a URL.

```php
VideoEmbed::detectPlatform('https://youtube.com/watch?v=dQw4w9WgXcQ');
// → 'youtube'

VideoEmbed::detectPlatform('https://vimeo.com/123');
// → 'vimeo'

VideoEmbed::detectPlatform('https://example.com');
// → null
```

### isSupported()

Check if a URL is a supported video platform.

```php
VideoEmbed::isSupported('https://youtube.com/watch?v=dQw4w9WgXcQ');
// → true

VideoEmbed::isSupported('https://example.com/video');
// → false
```

### Twig Functions and Filters

VideoEmbed is also available in Twig, as functions and as filters with the same names: `video_embed`, `video_id`, `video_platform` and `video_is_supported`.

```twig
{% verbatim %}{# Convert URL to embed URL #}
{{ video_embed(video_url, {autoplay: true}) }}
{{ video_url|video_embed }}
{{ video_url|video_embed({autoplay: true, loop: true}) }}

{# Get the video ID #}
{{ video_id(video_url) }}

{# Get platform name #}
{{ video_url|video_platform }}
{# → 'youtube' or 'vimeo' or null #}

{# Print the player only for a supported URL #}
{% if video_is_supported(video_url) %}
  <iframe src="{{ video_embed(video_url) }}"></iframe>
{% endif %}{% endverbatim %}
```

### Usage in ACF Blocks

```php
use Studiometa\Foehn\Helpers\VideoEmbed;

public function compose(array $block, array $fields): array
{
    $context = $fields;

    if (!empty($fields['video_url'])) {
        $context['embed_url'] = VideoEmbed::embedUrl($fields['video_url'], [
            'autoplay' => $fields['autoplay'] ?? false,
            'loop' => $fields['loop'] ?? false,
        ]);
        $context['platform'] = VideoEmbed::detectPlatform($fields['video_url']);
    }

    return $context;
}
```

## Logging

Føhn ships no logger. `error_log()` writes to the WordPress debug log when `WP_DEBUG` and `WP_DEBUG_LOG` are on, and `wp foehn log` reads that log. See [The debug log](/guide/troubleshooting#the-debug-log).

```php
error_log('Import finished: ' . $count . ' products');
```

To use a logger class, install a package for it, for example `tempest/log`, and configure it first. Føhn does not install it.

## Validation

For data validation, we recommend using a dedicated third-party package:

- **[rakit/validation](https://github.com/rakit/validation)** — Laravel-style validation
- **[respect/validation](https://github.com/Respect/Validation)** — Fluent validation library

### Example with rakit/validation

```bash
composer require rakit/validation
```

```php
use Rakit\Validation\Validator;

$validator = new Validator();

$validation = $validator->make($request->get_params(), [
    'name' => 'required|min:2',
    'email' => 'required|email',
    'message' => 'required|min:10',
]);

$validation->validate();

if ($validation->fails()) {
    $errors = $validation->errors()->firstOfAll();
    return new WP_REST_Response(['errors' => $errors], 422);
}

$data = $validation->getValidData();
```

## Related

- [Kernel](./kernel)
- [CacheInterface](./cache-interface)
- [Guide: Installation](/guide/installation)
