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
| `#[AsCron]`                                                     | The class has no public `__invoke()` method, or the cron expression is not valid                    |
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

In the `local` and `development` environments, Føhn writes the errors of the site to a log, and `wp foehn errors` prints the last one. An agent that changed code reads it after it loads a page, instead of a browser or the PHP log. For the JavaScript errors of the page, see [Browser errors](#browser-errors).

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

| Field             | Content                                                                                                                    |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `time`            | UTC, ISO 8601                                                                                                              |
| `level`           | `exception`, `fatal`, `warning` or `notice`                                                                                |
| `class`           | The exception class, or the PHP error type, such as `E_WARNING`                                                            |
| `message`         | The message, cut after 2000 characters                                                                                     |
| `file`, `line`    | Where it was thrown or raised, relative to the Composer project or to WordPress                                            |
| `twig`            | `template:line` in the Twig template, when the error comes from a view                                                     |
| `previous`        | The previous exceptions, as `Class: message`, up to 3                                                                      |
| `stack`           | The frames of the theme's own code in call order, then `+N other frames` for WordPress, plugins and packages               |
| `method`, `url`   | The HTTP method and the request URI, or `CLI` and the WP-CLI command                                                       |
| `template`        | The WordPress template Føhn matched, such as `single-product`                                                              |
| `controller`      | The template controller that handles it, or `null`                                                                         |
| `view`, `context` | The last Twig view Timber rendered, and the keys of its context (up to 40)                                                 |
| `requestId`       | The id of the traced request, as its `X-Foehn-Trace` header gives it, or `null`. See [Request Trace](/guide/request-trace) |

### The command

| Option             | Effect                                                                                    |
| ------------------ | ----------------------------------------------------------------------------------------- |
| `--limit=<n>`      | Print the last `n` entries, the newest first. Defaults to 1                               |
| `--level=<levels>` | Only these levels, separated by commas: `exception,fatal`                                 |
| `--since=<date>`   | Only entries recorded at or after this date: `2026-11-12 10:00`, `-10 minutes`, `-1 hour` |
| `--request=<id>`   | Only the entries of one traced request: the id `wp foehn trace` prints                    |
| `--format=json`    | Every field of each entry, as a JSON array on one line                                    |
| `--clear`          | Delete the log                                                                            |

The text cuts a message after 300 characters and gives the first 5 lines of the stack. JSON gives the whole message and every frame.

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

## Browser errors

The error log holds the errors of PHP. The errors of the JavaScript that runs in the page go to the browser console, which an agent in a terminal cannot read. While `npm run dev` runs, the Vite dev server writes them to a log in the theme, and `wp foehn errors --browser` prints them:

```bash
wp foehn errors --browser
```

```
TypeError: card is null
  error at 2026-11-12 15:48:50 UTC in theme/assets/js/components/Card.ts:12
    theme/assets/js/components/Card.ts:12 Card.mounted()
    theme/assets/js/app.js:4
    +3 other frames
```

### How it works

Vite 8 has the `server.forwardConsole` option: the Vite client in the page sends the console errors and warnings, the uncaught errors and the unhandled promise rejections to the dev server, which prints them in its terminal. Vite turns it on only when it runs inside a coding agent. The agent that edits the theme usually does not run `npm run dev` itself, and it cannot read the terminal of another process. So `@studiometa/foehn-vite-plugin` turns the option on, and also writes each event to `dist/browser-errors.jsonl` in the theme, beside the `hot` file. The PHP page loads the Vite client from the URL in the `hot` file, so this works for the pages WordPress renders, opened on the DDEV URL or on the Vite URL.

It needs Vite 8. With Vite 6 or 7, the page sends nothing and the log stays empty. Set `server.forwardConsole: false` in `vite.config.js` to turn off the terminal output and the log.

### The fields of an entry

| Field          | Content                                                                                                     |
| -------------- | ----------------------------------------------------------------------------------------------------------- |
| `time`         | UTC, ISO 8601                                                                                               |
| `level`        | `error` (uncaught error), `rejection` (unhandled promise rejection) or `console` (a console call)           |
| `class`        | The error name, such as `TypeError`, or the console method, such as `console.warn`                          |
| `message`      | The message, cut after 2000 characters                                                                      |
| `file`, `line` | The first frame of the project's own code, relative to the Vite root, with the line of the source file      |
| `stack`        | The frames of the project's own code (8 at most), then `+N other frames` for dependencies and other origins |

Lines are the lines of the source file: the plugin maps them back through the source map of the module, so a TypeScript or Vue file gives the line you edit. A console call has no stack, and an entry has no URL: Vite does not send the page.

### The command

`--browser` reads the browser log in place of the error log. `--limit`, `--since`, `--format=json` and `--clear` work as for the error log. `--level` takes `error`, `rejection` and `console`. `--request` does not apply: a browser entry has no request id.

The log is in the `dist` directory of the active (parent) theme, where `ViteManifest::fromTheme()` reads the `hot` file. It has the cap of the error log: 3 files of 256 KB. `npm run build` empties `dist`, and the log with it. An entry holds console messages, and the web server can serve `dist`: the log exists only where the dev server runs.

## The debug log

The error log holds the errors of the theme's own code. A warning of WordPress, a plugin or a package, an `error_log()` message and an error raised before the theme boots go to the debug log of WordPress. `wp foehn log` reads it as entries, and groups the entries that repeat.

```bash
wp foehn log
```

```
Undefined variable $price
  warning ×12 at 2026-11-12 10:42:13 UTC in theme/app/Controllers/ProductController.php:24, first at 2026-11-12 10:00:00 UTC

Uncaught RuntimeException: Boom
  fatal at 2026-11-12 10:00:05 UTC in theme/app/Foo.php:12
    #0 web/wp/wp-includes/class-wp-hook.php(324): App\Foo->run()
    #1 web/wp/wp-includes/plugin.php(517): WP_Hook->do_action()
    #2 theme/functions.php(8): do_action()
    +2 more lines
```

### Which file it reads

WordPress writes the log when `WP_DEBUG` and `WP_DEBUG_LOG` are on: `wp_debug_mode()` points PHP's `error_log` setting at `wp-content/debug.log`, or at the path `WP_DEBUG_LOG` holds. So the file holds PHP's warnings, notices, deprecations and fatal errors, and the `error_log()` messages of WordPress, plugins and the theme. The `wp-config.php` of a Føhn project sets `WP_DEBUG_LOG` to the value of `WP_DEBUG`: set `WP_DEBUG=true` in `.env` to turn the log on.

When `WP_DEBUG_LOG` is off, the command says how to turn it on and exits with an error. When `WP_DEBUG` is off and the file exists, it prints the entries and says that WordPress writes nothing new to the file. When the user that runs WP-CLI cannot read the file, it says so and exits with an error.

### Entries and groups

An entry starts with the date PHP writes, such as `[12-Nov-2026 10:00:00 UTC]`. The lines without a date that follow belong to it: the stack trace of an uncaught exception, or a message of several lines such as an `error_log(print_r($value, true))`. Xdebug writes each frame of its stack trace with a date of its own; these frames go to the trace of the entry above.

| Level        | Entries                                                             |
| ------------ | ------------------------------------------------------------------- |
| `fatal`      | `PHP Fatal error`, `PHP Parse error`, `PHP Recoverable fatal error` |
| `warning`    | `PHP Warning`                                                       |
| `notice`     | `PHP Notice`, such as `_doing_it_wrong()`                           |
| `deprecated` | `PHP Deprecated`                                                    |
| `log`        | A message without a PHP error type, such as an `error_log()` call   |

Entries with the same level, message, file and line are one group. The groups seen last come first.

| Field           | Content                                                                    |
| --------------- | -------------------------------------------------------------------------- |
| `level`         | One of the levels above                                                    |
| `message`       | The message, without the file and line PHP adds, cut after 2000 characters |
| `file`, `line`  | Where PHP raised it, relative to the Composer project or to WordPress      |
| `count`         | How many entries the group has                                             |
| `first`, `last` | When the first and the last entry were written, UTC, ISO 8601              |
| `trace`         | The stack trace of the last entry, up to 50 lines                          |

### The command

| Option             | Effect                                                                                   |
| ------------------ | ---------------------------------------------------------------------------------------- |
| `--limit=<n>`      | Print `n` groups. Defaults to 20                                                         |
| `--level=<levels>` | Only these levels, separated by commas: `fatal,warning`                                  |
| `--since=<date>`   | Only entries written at or after this date: `2026-11-12 10:00`, `-10 minutes`, `-1 hour` |
| `--grep=<text>`    | Only entries whose message, file or stack trace contains this text. Case does not matter |
| `--format=json`    | Every field of each group, as a JSON array on one line                                   |

The filters apply to the entries before they are grouped, so `count`, `first` and `last` count only the entries they keep. The text cuts a message after 300 characters and gives the first 3 lines of a trace.

A debug log can grow to gigabytes. The command reads only the last 8 MB of the file, and says so when it cuts it: in the text, or on standard error with `--format=json`. Nothing deletes or rotates the file: it belongs to WordPress.

## Related

- [Request Trace](/guide/request-trace)
- [CLI Commands](/guide/cli-commands#project-inspection)
- [Discovery Cache](/guide/discovery-cache)
- [ViewEngineInterface](/api/view-engine-interface)
