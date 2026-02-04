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

## Related

- [Kernel](./kernel)
- [Guide: Installation](/guide/installation)
