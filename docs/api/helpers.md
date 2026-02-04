# Helpers

Global helper functions provided by WP Tempest.

## app()

Get the kernel instance or a service from the container.

```php
use function Studiometa\WPTempest\app;

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
use function Studiometa\WPTempest\config;

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
use Studiometa\WPTempest\Contracts\ViewEngineInterface;
use function Studiometa\WPTempest\app;

// In a template or function
$view = app(ViewEngineInterface::class);
$html = $view->render('partials/card', ['title' => 'Hello']);
```

### Getting Kernel Properties

```php
use function Studiometa\WPTempest\app;

$kernel = app();
$appPath = $kernel->getAppPath();
$isBooted = $kernel->isBooted();
```

### Configuration Access

```php
use function Studiometa\WPTempest\config;

// Access nested configuration
$apiKey = config('services.stripe.key');
$timeout = config('http.timeout', 30);
```

## Namespace

The helpers are in the `Studiometa\WPTempest` namespace:

```php
// Full namespace
\Studiometa\WPTempest\app();
\Studiometa\WPTempest\config('key');

// Or import
use function Studiometa\WPTempest\app;
use function Studiometa\WPTempest\config;
```

## Related

- [Kernel](./kernel)
- [Guide: Installation](/guide/installation)
