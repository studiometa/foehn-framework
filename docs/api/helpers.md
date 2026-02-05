# Helpers

Global helper functions provided by Foehn.

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

Get a configuration value from the kernel.

```php
use function Studiometa\Foehn\config;

// Get a config value
$viewsPath = config('timber.views');

// With default
$debug = config('app.debug', false);
```

### Signature

```php
function config(string $key, mixed $default = null): mixed
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
use function Studiometa\Foehn\config;

// Access nested configuration
$apiKey = config('services.stripe.key');
$timeout = config('http.timeout', 30);
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

$results = WP::db()->get_results("SELECT * FROM {$wpdb->posts} LIMIT 10");
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

### Why Use This?

Using `$GLOBALS` directly triggers static analysis warnings (e.g., Mago's `no-global` rule). This helper:

- Provides typed return values for better IDE support
- Centralizes unsafe access in one auditable location
- Makes code easier to test (can mock the helper)
- Follows Tempest's helper class patterns

## Env

Helper class for environment detection. Supports multiple env variable conventions (APP_ENV, WP_ENV) with safe defaults.

### get()

Get the current environment name.

```php
use Studiometa\Foehn\Helpers\Env;

$env = Env::get();
// Checks APP_ENV, then WP_ENV, falls back to 'production'
```

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

Check if running in a local environment (returns true for both 'local' and 'development').

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

#[AsContextProvider(templates: ['*'])]
final class GlobalContext implements ContextProviderInterface
{
    public function provide(array $context): array
    {
        $context['is_production'] = Env::isProduction();
        $context['is_debug'] = Env::isDebug();
        $context['environment'] = Env::get();

        return $context;
    }
}
```

Then in Twig:

```twig
{% if is_debug %}
    {{ dump(post) }}
{% endif %}

{% if not is_production %}
    <div class="env-banner">Environment: {{ environment }}</div>
{% endif %}
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
VideoEmbed::detectPlatform('https://youtube.com/watch?v=xxx');
// → 'youtube'

VideoEmbed::detectPlatform('https://vimeo.com/123');
// → 'vimeo'

VideoEmbed::detectPlatform('https://example.com');
// → null
```

### isSupported()

Check if a URL is a supported video platform.

```php
VideoEmbed::isSupported('https://youtube.com/watch?v=xxx');
// → true

VideoEmbed::isSupported('https://example.com/video');
// → false
```

### Twig Filters

VideoEmbed also provides Twig filters:

```twig
{# Convert URL to embed URL #}
{{ video_url|video_embed }}
{{ video_url|video_embed({autoplay: true, loop: true}) }}

{# Get platform name #}
{{ video_url|video_platform }}
{# → 'youtube' or 'vimeo' or null #}
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

## Cache

Helper class for caching data using WordPress transients.

### get() / set()

Store and retrieve values from cache.

```php
use Studiometa\Foehn\Helpers\Cache;

// Store a value (TTL in seconds)
Cache::set('key', $value, 3600);

// Retrieve a value
$value = Cache::get('key');
$value = Cache::get('key', 'default');
```

### has()

Check if a key exists in cache.

```php
if (Cache::has('key')) {
    // ...
}
```

### remember()

Get from cache or compute and store.

```php
use Studiometa\Foehn\Helpers\Cache;

$posts = Cache::remember('recent_posts', 3600, function () {
    return get_posts(['numberposts' => 10]);
});
```

### forget()

Remove a value from cache.

```php
Cache::forget('key');
```

### forever()

Store a value with no expiration.

```php
Cache::forever('key', $value);
```

### increment() / decrement()

Modify numeric values.

```php
Cache::increment('counter');
Cache::increment('counter', 5);
Cache::decrement('counter');
```

### Prefix

All keys are prefixed with `foehn_` by default.

```php
Cache::setPrefix('myapp_');
```

## Log

Helper class for logging to WordPress debug.log.

### Log Levels

```php
use Studiometa\Foehn\Helpers\Log;

Log::emergency('System is unusable');
Log::alert('Action must be taken immediately');
Log::critical('Critical conditions');
Log::error('Error conditions');
Log::warning('Warning conditions');
Log::notice('Normal but significant conditions');
Log::info('Informational messages');
Log::debug('Debug-level messages');
```

### Context

Pass additional data as context:

```php
Log::info('User logged in', ['user_id' => 123, 'ip' => $ip]);
Log::error('Payment failed', ['order_id' => 456, 'error' => $e->getMessage()]);
```

### Output Format

Messages are formatted as:

```
[2026-02-05 15:30:00] [FOEHN.INFO] User logged in {"user_id":123}
```

### Enabling Logging

Logging only works when `WP_DEBUG_LOG` is enabled in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Validator

Laravel-style validation helper.

### Basic Usage

```php
use Studiometa\Foehn\Helpers\Validator;

$validator = Validator::make($data, [
    'email' => 'required|email',
    'name' => 'required|min:2|max:100',
    'age' => 'numeric|min:18',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
}

$validated = $validator->validated();
```

### Quick Validation

Throw exception on failure:

```php
use Studiometa\Foehn\Helpers\Validator;
use Studiometa\Foehn\Helpers\ValidationException;

try {
    $data = Validator::validate($request, [
        'email' => 'required|email',
    ]);
} catch (ValidationException $e) {
    $errors = $e->errors();
}
```

### Available Rules

| Rule              | Description                                     |
| ----------------- | ----------------------------------------------- |
| `required`        | Field must be present and not empty             |
| `email`           | Must be a valid email address                   |
| `url`             | Must be a valid URL                             |
| `numeric`         | Must be a number                                |
| `integer`         | Must be an integer                              |
| `string`          | Must be a string                                |
| `array`           | Must be an array                                |
| `boolean`         | Must be true/false                              |
| `min:n`           | Minimum length (string) or value (number)       |
| `max:n`           | Maximum length (string) or value (number)       |
| `between:min,max` | Value must be between min and max               |
| `in:a,b,c`        | Value must be one of the listed values          |
| `regex:/pattern/` | Must match regex pattern                        |
| `confirmed`       | Field must have matching `{field}_confirmation` |
| `nullable`        | Field can be null                               |

### Multiple Rules

Combine rules with `|`:

```php
$rules = [
    'email' => 'required|email|max:255',
    'password' => 'required|min:8|confirmed',
    'role' => 'required|in:admin,editor,author',
];
```

### REST API Example

```php
use Studiometa\Foehn\Attributes\AsRestRoute;
use Studiometa\Foehn\Helpers\Validator;
use Studiometa\Foehn\Helpers\ValidationException;
use WP_REST_Request;
use WP_REST_Response;

final class ContactApi
{
    #[AsRestRoute('theme/v1', '/contact', 'POST', permission: 'public')]
    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $data = Validator::validate($request->get_params(), [
                'name' => 'required|min:2',
                'email' => 'required|email',
                'message' => 'required|min:10',
            ]);
        } catch (ValidationException $e) {
            return new WP_REST_Response([
                'errors' => $e->errors(),
            ], 422);
        }

        // Process valid data...

        return new WP_REST_Response(['success' => true]);
    }
}
```

## Related

- [Kernel](./kernel)
- [Guide: Installation](/guide/installation)
