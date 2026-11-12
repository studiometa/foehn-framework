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

## Related

- [CLI Commands](/guide/cli-commands#project-inspection)
- [Discovery Cache](/guide/discovery-cache)
- [ViewEngineInterface](/api/view-engine-interface)
