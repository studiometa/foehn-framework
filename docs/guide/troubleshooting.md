# Problems and Errors

Føhn reports what goes wrong in the commands you already run: `wp foehn inspect`, `wp foehn discovery:status` and the error a failed render throws. Each message says what is wrong and, when the cause is known, gives the fix on one line, after `Fix:`.

## Classes discovery skips

A class that carries an attribute but breaks the contract of that attribute is not registered. Discovery skips it, records why, and registers every other class. One broken class does not stop the site.

| Attribute                                                       | What makes discovery skip the class                                                                 |
| --------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `#[AsPostType]`                                                 | The class does not extend `Timber\Post`                                                             |
| `#[AsTaxonomy]`                                                 | The class does not extend `Timber\Term`                                                             |
| `#[AsTimberModel]`                                              | The class extends neither `Timber\Post` nor `Timber\Term`                                           |
| `#[AsBlock]`, `#[AsContextProvider]`, `#[AsTemplateController]` | The class does not implement the interface of the attribute                                         |
| `#[AsBlockBinding]`                                             | The class does not implement the interface, or the source name has no namespace                     |
| `#[AsSettingsPage]`                                             | The class does not implement the interface, or it declares no form                                  |
| `#[AsCliCommand]`, `#[AsSeeder]`                                | The class does not implement the interface of the attribute                                         |
| `#[AsTwigExtension]`                                            | The class does not extend `Twig\Extension\AbstractExtension`                                        |
| `#[AsCron]`                                                     | The class has no public `__invoke()` method                                                         |
| `#[AsJob]`                                                      | `__invoke()` is missing, or it does not take one parameter typed with a class                       |
| `#[AsRewriteRule]`                                              | `after` is not `top` or `bottom`                                                                    |
| `#[AsPostMeta]`                                                 | The type, the object type, the REST schema or the sanitizer is not valid (only that key is skipped) |
| `#[AsAcfBlock]`, `#[AsAcfFieldGroup]`                           | The class does not implement the interface of the attribute                                         |
| `hooks` in `foehn.config.php`                                   | The class does not exist                                                                            |

The records are discovery items, so the [discovery cache](/guide/discovery-cache) stores them with the rest: a request served from the cache knows what the scan skipped. When `debug` is on in the [configuration](/guide/configuration), each one is also raised as a PHP warning on every request.

## Reading the problems

`wp foehn inspect` starts its summary with them:

```
problems — 2: App\Models\Gallery #[AsPostType], App\Models\Article #[AsPostType]
post-types — 1: article
...

Problems with their fix: wp foehn inspect problems
```

`wp foehn inspect problems` prints each one in full, and `--format=json` gives `class`, `attribute`, `reason` and `fix` for each row:

```bash
wp foehn inspect problems --format=json
```

```json
{
  "problems": [
    {
      "class": "App\\Models\\Gallery",
      "attribute": "#[AsPostType]",
      "reason": "App\\Models\\Gallery has #[AsPostType] but does not extend Timber\\Post, so the post type is not registered.",
      "fix": "Declare App\\Models\\Gallery with `extends \\Timber\\Post`."
    }
  ]
}
```

`fix` is `null` when the cause is not known: a class named in `hooks` whose discovery threw gives the message of the exception only.

The `problems` section of `inspect` also lists the links between post types and taxonomies that point at nothing: a post type that names a taxonomy in `taxonomies`, or a taxonomy that names a post type in `postTypes`, when no Føhn attribute declares it and WordPress does not register it. WordPress ignores such a link without a word. Core types such as `category` and the types of plugins count as registered. These rows are computed when `inspect` runs, so they are not in `discovery:status`.

`wp foehn discovery:status` prints the classes discovery skipped, one line each:

```
Problems: 1
  App\Models\Gallery has #[AsPostType] but does not extend Timber\Post, so the post type is not registered. Fix: Declare App\Models\Gallery with `extends \Timber\Post`.
```

After a fix, run `wp foehn discovery:clear` when the discovery cache is on, so the next request scans the class again.

## Rendering errors

`TimberViewEngine` throws `Studiometa\Foehn\Views\ViewRenderingFailed`, a `RuntimeException`, when a view cannot be rendered. Its message names the view that was asked for, which is often not the template that failed.

A Twig error keeps the template and the line where Twig failed, and Twig's own message. The Twig error is the previous exception:

```
Rendering the view 'pages/single' failed in partials/card.twig at line 2: Unable to find template "partials/missing.twig" (looked into: /var/www/html/wp-content/themes/my-theme/templates).
```

A missing view names every view that was tried, the directories Timber searched, and the file to create:

```
No template found for the views 'pages/single-product', 'pages/single'. Searched pages/single-product.twig, pages/single.twig in: /var/www/html/wp-content/themes/my-theme, /var/www/html/wp-content/themes/my-theme/templates. Fix: create one of pages/single-product.twig, pages/single.twig in /var/www/html/wp-content/themes/my-theme/templates.
```

The directories are the ones Timber's loader searches, from `TimberConfig::$templatesDir` and the `timber/locations` filter. `exists()` asks the same loader, so a view `exists()` finds is a view `render()` finds.

## The error log

In the `local` and `development` environments, Føhn writes the errors of the site to a log, and `wp foehn errors` prints the last one. An agent that changed code reads it after it loads a page, instead of a browser or the PHP log.

```bash
wp foehn errors
```

```
Studiometa\Foehn\Views\ViewRenderingFailed: Rendering the view 'pages/404' failed in pages/404.twig at line 18: Variable "price" does not exist.
  exception at 2026-11-12 11:25:42 UTC in vendor/studiometa/foehn/src/Views/ViewRenderingFailed.php:67
  twig pages/404.twig:18
  caused by Twig\Error\RuntimeError: Variable "price" does not exist in "pages/404.twig" at line 18.
  GET /no-such-page/ · template 404 · controller App\Controllers\Error404Controller · view pages/404.twig
  context site, theme, user, menus, post, posts, current_year
    theme/app/Controllers/Error404Controller.php:21 Studiometa\Foehn\Views\TimberViewEngine->render()
    +7 other frames
```

### What is recorded

- each uncaught exception;
- each fatal error;
- each warning and notice raised in the theme's own code: a PHP file of the theme directory, or a Twig template of the theme. The `vendor` and `node_modules` directories of the theme are not its own code. A warning of WordPress, a plugin or a Composer package is not recorded.

A warning that `error_reporting()` leaves out is not recorded: a warning silenced with `@`, and a notice when `WP_DEBUG` is off, because WordPress then leaves notices out. Deprecations are not recorded: `wp foehn verify --profile=updates` reports them. One request writes each error once, and at most 20 entries.

Recording starts when the theme boots Føhn. An error raised before, in WordPress core or in a plugin that loads first, is not recorded. An exception that code catches is not recorded.

### The fields of an entry

| Field             | Content                                                                                                      |
| ----------------- | ------------------------------------------------------------------------------------------------------------ |
| `time`            | UTC, ISO 8601                                                                                                |
| `level`           | `exception`, `fatal`, `warning` or `notice`                                                                  |
| `class`           | The exception class, or the PHP error type, such as `E_WARNING`                                              |
| `message`         | The message, cut after 2000 characters                                                                       |
| `file`, `line`    | Where it was thrown or raised, relative to the Composer project or to WordPress                              |
| `twig`            | `template:line` in the Twig template, when the error comes from a view                                       |
| `previous`        | The previous exceptions, as `Class: message`, up to 3                                                        |
| `stack`           | The frames of the theme's own code in call order, then `+N other frames` for WordPress, plugins and packages |
| `method`, `url`   | The HTTP method and the request URI, or `CLI` and the WP-CLI command                                         |
| `template`        | The WordPress template Føhn matched, such as `single-product`                                                |
| `controller`      | The template controller that handles it, or `null`                                                           |
| `view`, `context` | The last Twig view Timber rendered, and the keys of its context (up to 40)                                   |
| `requestId`       | Reserved for a request id. Always `null` for now                                                             |

### The command

| Option             | Effect                                                                                    |
| ------------------ | ----------------------------------------------------------------------------------------- |
| `--limit=<n>`      | Print the last `n` entries, the newest first. Defaults to 1                               |
| `--level=<levels>` | Only these levels, separated by commas: `exception,fatal`                                 |
| `--since=<date>`   | Only entries recorded at or after this date: `2026-11-12 10:00`, `-10 minutes`, `-1 hour` |
| `--format=json`    | Every field of each entry, as a JSON array on one line                                    |
| `--clear`          | Delete the log                                                                            |

The text gives the first 5 lines of the stack. JSON gives all of them.

### The log file

The log is `wp-content/cache/foehn/errors/errors.jsonl`, one JSON object per line. When the next entry would make it larger than 256 KB, it becomes `errors.1.jsonl` and the oldest file is deleted. The log never holds more than 3 files.

### When errors are recorded

`FoehnConfig::$recordErrors` decides. With the default, `null`, errors are recorded when the environment (`WP_ENVIRONMENT_TYPE`) is `local` or `development`. Set it to `true` to record them in another environment, such as a staging site, and to `false` to never record them:

```php
// app/foehn.config.php
return new FoehnConfig(
    recordErrors: true,
);
```

An entry holds paths, messages and URLs. The cache directory can be served by the web server: do not turn recording on where people you do not trust can read the files of `wp-content`.

### How it hooks into PHP and WordPress

The recorder adds a PHP error handler, a PHP exception handler and a shutdown function, and it changes nothing in how PHP and WordPress handle the error:

- The error handler records the warning, then gives it to the handler that was there before, or to PHP.
- The exception handler records the exception, then gives it to the handler that was there before. With none, it throws the exception again, so PHP ends the request with its own fatal error.
- The shutdown function reads `error_get_last()`. WordPress's fatal error handler (`WP_Fatal_Error_Handler`) reads the same value in its own shutdown function, which runs first, and shows its error page as usual.

## Related

- [CLI Commands](/guide/cli-commands#project-inspection)
- [Discovery Cache](/guide/discovery-cache)
- [ViewEngineInterface](/api/view-engine-interface)
