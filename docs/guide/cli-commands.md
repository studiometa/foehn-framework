# CLI Commands

Føhn provides `#[AsCliCommand]` for creating WP-CLI commands and includes built-in scaffolding commands.

## Built-in Commands

Føhn includes scaffolding and discovery management commands:

### Scaffolding Commands

```bash
# Generate a Timber model (with optional post type)
wp foehn make:model Product
wp foehn make:model Product --post-type

# Generate a post type, with meta fields and a URL slug
wp foehn make:post-type product
wp foehn make:post-type event --rewrite-slug=events --meta=start_date,venue,capacity:integer

# Generate a taxonomy
wp foehn make:taxonomy ProductCategory --post-types=product

# Generate an ACF block
wp foehn make:acf-block Hero

# Generate a native block
wp foehn make:block Counter --interactive

# Generate a template controller
wp foehn make:controller single-product

# Generate a hooks class
wp foehn make:hooks Seo

# Generate a context provider
wp foehn make:context-provider Header

# Generate a context provider
wp foehn make:context GlobalContext --global
wp foehn make:context ProductContext --templates=single-product,archive-product

# Generate a block pattern
wp foehn make:pattern HeroWithCta

# Generate a shortcode
wp foehn make:shortcode Button

# Generate an ACF field group
wp foehn make:field-group ProductFields --post-type=product
wp foehn make:field-group PageFields --page-template=front-page
wp foehn make:field-group CategoryFields --taxonomy=category

# Generate an ACF options page
wp foehn make:options-page ThemeSettings
wp foehn make:options-page FooterSettings --parent=theme-settings

# Generate a navigation menu
wp foehn make:menu HeaderMenu --location=header
wp foehn make:menu FooterMenu --description="Footer Navigation"

# Generate an image size
wp foehn make:image-size CardImage --width=400 --height=300 --crop
wp foehn make:image-size HeroImage --width=1920 --height=0

# Generate a seeder
wp foehn make:seeder events --description="Sample events" --order=20
```

### Global Options

All scaffolding commands, the `foehn-acf` ones included, support these options:

```bash
--force          # Overwrite an existing file
--dry-run        # Write nothing: show the file that would be written
--format=json    # Print the result as JSON (default: text)
```

Example with dry-run:

```bash
wp foehn make:post-type product --dry-run
```

### Where a Generated Class Goes

A scaffolding command puts its class next to the classes of the same kind the project already declares. It reads them from discovery: a post type goes where the project's `#[AsPostType]` classes are, a block where its `#[AsBlock]` classes are, and so on.

- The directory is the one that holds most of them. When each one has a directory of its own (`Blocks/Hero/HeroBlock.php`, `Blocks/Quote/QuoteBlock.php`), it is the directory that holds them all (`Blocks/`).
- The namespace is theirs.
- The parent class is the one most of them extend, when the stub extends a class. A post type of a project whose post types extend `App\Models\BaseContent` extends it too.

Only the classes under the app directory count, not the classes of the framework or of a package. When the project declares nothing of the kind, the command uses its default directory (`Models/` for a post type, `Blocks/` for a block…) and the stub's parent class. Run `wp foehn discovery:clear` first if the discovery cache is older than the classes you just added.

### Post Types

`make:post-type` writes a model that registers the post type and its meta fields:

```bash
wp foehn make:post-type event --rewrite-slug=events --meta=start_date,venue,capacity:integer
```

| Option                   | Effect                                                                                                                                                                                                                                                                                                                             |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--class=<class>`        | The class name. Default: the PascalCase name (`event` gives `Event`).                                                                                                                                                                                                                                                              |
| `--singular`, `--plural` | The labels. Default: the humanized name, and the singular with `s`.                                                                                                                                                                                                                                                                |
| `--rewrite-slug=<slug>`  | The URL slug of the single pages and the archive, as `rewriteSlug` of `#[AsPostType]`. Default: the name.                                                                                                                                                                                                                          |
| `--meta=<fields>`        | Meta fields, comma-separated, each `key` or `key:type`. The type is `string` (default), `integer`, `number` or `boolean`. Each field gets a `#[AsPostMeta]` and an accessor that reads it: `start_date` gives `startDate(): ?string`. The post type then supports `custom-fields`, so the block editor reads and saves the fields. |

The class extends `Studiometa\Foehn\Models\Post`, which adds the query methods to `Timber\Post`, unless the project's post types share another parent (see above).

- Store a date as a string in `Y-m-d`, or `Y-m-d H:i:s`, so a `meta_query` can compare it with the type `DATE` or `DATETIME`.
- An `array` or `object` field needs a REST schema. Declare it with `#[AsPostMeta]` by hand.
- No accessor is written when its name is a method of the parent class: a field `date` would replace `Timber\Post::date()`, which Timber and the templates call. The field is declared, and a note says to read it with `post.meta('date')` or to give the accessor another name.

The notes name the Twig files to create. For each of `single-<name>` and `archive-<name>`, they give the template controller that claims it, and a file next to the views of the same kind in the theme: the views of the other post types (`pages/single-project.twig`), or else the generic one (`pages/single.twig`). The controller picks the view at runtime, so read its `render()` or `renderFirst()` call to confirm. The notes also say where editors edit the meta fields (the Custom Fields panel, or blocks bound with `core/post-meta`), and that the rewrite rules need `wp foehn rewrite:flush`.

### Scripts and Coding Agents

A scaffolding command never asks a question. Every value has a default or comes from an option, so a script or a coding agent can run it without a terminal:

- A missing name is an error. The command writes nothing and exits with status `1`.
- A file that already exists is an error, unless you give `--force`. The command leaves the file as it is and exits with status `1`. A `--dry-run` reports the same error, because the real run would refuse.
- An unknown `--format` is an error, before anything is generated.

With `--format=json`, the command prints one JSON object on standard output. Errors go to standard error.

```bash
wp foehn make:block hero --format=json
```

```json
{
  "dryRun": false,
  "files": [{ "path": "/var/www/html/theme/app/Blocks/HeroBlock.php", "status": "created" }],
  "notes": ["Create the Twig template: templates/blocks/hero.twig"]
}
```

| Key                | Value                                                                                                                  |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------- |
| `dryRun`           | `true` when `--dry-run` was given.                                                                                     |
| `files[].path`     | The absolute path of the file.                                                                                         |
| `files[].status`   | `created`, `overwritten` or `exists` (left as it is). With `--dry-run`: `would-create`, `would-overwrite` or `exists`. |
| `files[].contents` | With `--dry-run` only: the contents the command would write.                                                           |
| `notes`            | The next steps, for example the Twig file to create. Can be empty.                                                     |

When the status is `exists`, the JSON is printed and the command then fails with status `1`.

### Project Inspection

```bash
# What the project declares, by name: a summary to read whole
wp foehn inspect

# Some sections in full, as JSON for a program or a coding agent
wp foehn inspect post-types templates menus --format=json

# Every section in full
wp foehn inspect --all --format=json
```

`inspect` reports the project model from what discovery found. It reads the attributes the code declares, so it registers nothing and writes nothing. Run it before you write code in a project you do not know.

With no section, `inspect` prints a summary: each section with the names of its rows (post type names, WordPress templates, Twig views, menu locations, command names…). It is short enough to read whole. It starts with `problems`; `wp foehn inspect problems` gives the reason and the fix of each one (see [Problems and Errors](/guide/troubleshooting)). Name one or more sections to print them in full, or pass `--all` for every section. `--all` cannot be combined with section names.

| Section             | What it lists                                                                                                       |
| ------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `problems`          | What will not register: the classes discovery skipped and the type links that point at nothing, with the fix        |
| `post-types`        | Post types: the Timber model class, the arguments of `#[AsPostType]`, the taxonomies and the `#[AsPostMeta]` fields |
| `taxonomies`        | Taxonomies: the Timber model class, the arguments of `#[AsTaxonomy]`, the post types and the term meta fields       |
| `settings`          | `#[AsSettingsPage]` pages and the options they store: option name, type, default and sanitizer                      |
| `blocks`            | `#[AsBlock]` blocks: name, title, class, category, and each attribute with its type, default and allowed values     |
| `patterns`          | `#[AsBlockPattern]` patterns: name, title, class and Twig template                                                  |
| `bindings`          | `#[AsBlockBinding]` sources: name, label, class and the block context they use                                      |
| `templates`         | Each WordPress template a controller claims: the controller that renders it, and its context keys                   |
| `views`             | Each Twig file of the theme: the context providers its view name matches, and its context keys                      |
| `context-providers` | Context providers, the view names they match, and the keys each one adds                                            |
| `menus`             | Menu locations and the Twig variable of each one (`menus.<location>`)                                               |
| `image-sizes`       | Image sizes, with the name WordPress registers them under                                                           |
| `rewrite-rules`     | `#[AsRewriteRule]` rules: regex, query, query vars and class                                                        |
| `rest-routes`       | `#[AsRestRoute]` routes: path, HTTP method, permission and handler method                                           |
| `cli-commands`      | Every `wp foehn` command, the framework's own included                                                              |
| `seeders`           | `#[AsSeeder]` seeders, in the order `wp foehn seed` runs them: class, order and description                         |
| `plugins`           | Active, network-activated and must-use plugins, with their versions                                                 |
| `theme-supports`    | Every `add_theme_support()` feature, with its arguments                                                             |
| `vite`              | The entry points of the theme build (`dist/.vite/manifest.json`), and the dev server URL while `npm run dev` runs   |

A post type or taxonomy that Føhn does not register, such as `post`, `category` or a plugin's post type, is listed when a `#[AsTimberModel]` maps it to a class or a `#[AsPostMeta]` names it as its subtype. Its `declared` field is then `false`, `model` is `null` when no Timber model maps it, and the arguments only the registering code knows are `null`. A `#[AsPostMeta]` field with no subtype applies to every post type or taxonomy, so it is listed on each of them.

#### Where content is stored

Three sections say where content goes, so you can write it with core WP-CLI commands instead of guessing keys:

- `post-types` and `taxonomies`: each `#[AsPostMeta]` field gives its `key`, `type`, `default` and `single` (`false` means one row per value). Write it with `wp post meta update <id> <key> <value>` or `wp term meta update`.
- `settings`: each setting gives its `option` name, `type`, `default` and `sanitize` callback. Write it with `wp option update <option> <value>`. Every setting has a sanitizer: the one the page declares (`sanitizeDeclared: true`) or the one its type implies, such as `sanitize_text_field` for a string. Føhn calls `register_setting()` on `init`, and WP-CLI fires `init` before it runs a command, so `wp option update` saves through the same sanitizer as the admin form (`sanitizeOnWpCli: true`). A command run with `--skip-themes` does not load Føhn, and then no sanitizer runs.
- `blocks`: each attribute gives its `name`, `type`, `default` and `enum`, which is what a block comment such as `<!-- wp:theme/hero {"align":"center"} /-->` in `post_content` may hold. The editor-only keys (`control`, `label`, …) are left out.

#### Template context

Two kinds of names meet in a template, and `inspect` reports them apart:

- `templates` has one row per WordPress template a `#[AsTemplateController]` claims, such as `single-product` or `archive-*`. `controller` is the class that renders it. When several controllers claim the same template, the lowest `priority` wins (on a tie, the one discovered last), and `overridden` lists the others.
- `views` has one row per Twig file in the theme's template directories (`TimberConfig::templatesDir`), named as `render()` names it: `templates/pages/single.twig` is `pages/single`. A child theme's file replaces the parent's file with the same name.

Context providers match view names, not WordPress templates. Which view a controller renders is decided at runtime (the starter's `SingleController` renders the first of `pages/single-{post-type}-{slug}`, `pages/single-{post-type}` and `pages/single` that exists), so `inspect` does not link a template to a view.

Both sections give the Twig variables that can be known without rendering. What all rows share is given once, next to the rows, so the sections stay short:

```json
{
  "templates": {
    "base": [
      "body_class",
      "http_host",
      "menus",
      "post",
      "posts",
      "site",
      "theme",
      "user",
      "wp_title"
    ],
    "controllers": {
      "App\\Controllers\\TermController": { "priority": 10, "keys": ["children"], "partial": false }
    },
    "templates": [
      {
        "template": "taxonomy-*",
        "controller": "App\\Controllers\\TermController",
        "overridden": [],
        "request": ["term"]
      }
    ]
  },
  "views": {
    "base": [
      "body_class",
      "http_host",
      "menus",
      "post",
      "posts",
      "site",
      "theme",
      "user",
      "wp_title"
    ],
    "providers": {
      "App\\ContextProviders\\CardContextProvider": {
        "priority": 10,
        "keys": ["price_label"],
        "partial": false
      },
      "App\\ContextProviders\\GlobalContextProvider": {
        "priority": 10,
        "keys": ["current_year"],
        "partial": false
      }
    },
    "everyView": ["App\\ContextProviders\\GlobalContextProvider"],
    "views": [
      {
        "view": "components/card",
        "file": "templates/components/card.twig",
        "providers": ["App\\ContextProviders\\CardContextProvider"]
      },
      { "view": "pages/single", "file": "templates/pages/single.twig", "providers": [] }
    ]
  }
}
```

| Field                      | What it holds                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `base`                     | Keys that are always set: `post`, `posts`, `site`, `user`, the Timber global context (`theme`, `http_host`, `wp_title`, `body_class`), and `menus` when the project declares a `#[AsMenu]`. A value can be `null` (`post` on an archive, `user` for a visitor)                                                                                                                                                                                                                                              |
| `templates[].request`      | Keys that `Timber::context()` adds for the request that template answers, on top of `base`: `term` on a category, tag or taxonomy archive, `search_query` on `search`, `author` on `author`. A wildcard that reaches several kinds of request adds none                                                                                                                                                                                                                                                     |
| `controllers`, `providers` | Each controller that claims a template, and each provider that matches a view, by class: its `priority`, its `keys` and `partial`. `keys` are read from the source of the class: each literal `->with('key', …)` call in the class, its parent classes and their traits. A call can sit in a condition, so a key is not guaranteed at runtime. `partial` is `true` when the class also sets keys that only exist at runtime: `with($name, …)`, `merge()` or `withDto()`. Its `keys` list is then incomplete |
| `everyView`                | The providers on `*`, which match every view name, in the order they run. They are not repeated on the rows                                                                                                                                                                                                                                                                                                                                                                                                 |
| `views[].providers`        | The other providers whose pattern matches that view, in the order they run. With `everyView`, they run in `priority` order                                                                                                                                                                                                                                                                                                                                                                                  |

The tables give the same data: `base` and the providers in `everyView` with their keys are printed once above the rows, and each row shows the keys of its controller or of its own providers.

Keys added elsewhere are not listed: a `timber/context` filter from a plugin, `ViewEngine::share()`, a helper class that a controller calls, and the keys a block's `compose()` returns. The controller's keys reach only the views it renders, and a Twig file included with `{% include %}` gets the context of the file that includes it.

With the `FULL` discovery cache, a class added after the cache was written is missing from the report until you run `wp foehn discovery:clear`.

JSON is printed on one line, without indentation. The full report has the section names as keys, also for one section, so a program reads `report["post-types"]` in all cases. Most sections are a list of rows; `templates`, `views` and `vite` are records that hold their rows (`templates`, `views`, `entries`) next to what the rows share. The summary has two keys: `summary`, an object with each section name as key and the list of its row names as value, and `commands`, the commands that print sections in full. An unknown section stops with an error that lists the valid names.

### Agent Skills

```bash
# The skills of the installed packages
wp foehn skill
wp foehn skill --format=json

# The main instructions of one skill
wp foehn skill foehn

# The reference files of a skill, then one of them
wp foehn skill foehn --list-references
wp foehn skill foehn --reference=blocks
```

`skill` prints the [Agent Skills](./ai-agents.md) that the installed packages ship, at the versions in your project. It reads files only.

The skills come from two places:

- every Composer package in `vendor/composer/installed.json` that has a `skills/<name>/SKILL.md` file. No package list is built in, so a third-party package that ships a skill is listed too;
- `node_modules/@studiometa/foehn-vite-plugin/skills`, when the Vite plugin is installed.

| Option               | Effect                                                                                                                            |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `<name>`             | Print the `SKILL.md` of this skill. Without it, the command lists the skills.                                                     |
| `--reference=<file>` | Print a file of the skill's `references/` directory instead. `blocks`, `blocks.md` and `references/blocks.md` give the same file. |
| `--list-references`  | List the reference files of the skill.                                                                                            |
| `--format=json`      | Print a list as JSON.                                                                                                             |

The table gives the skill `name`, the `package`, its installed `version` and the first sentence of the `description` (from the `SKILL.md` frontmatter). The JSON gives the full `description` and also the `path` of the file relative to the project root, such as `vendor/studiometa/foehn/skills/foehn/SKILL.md`. An agent can read that path directly when WordPress cannot boot.

When two packages ship a skill with the same name, both are listed and `wp foehn skill <name>` prints the first: Composer packages come before the Vite plugin, in package name order. An unknown skill or reference stops with an error that lists the valid names.

### Seeders

```bash
# Run every seeder, by order
wp foehn seed

# Run one seeder, by short or full class name
wp foehn seed --class=EventsSeeder

# List the seeders, in order, and run nothing
wp foehn seed --dry-run
```

`seed` runs the classes with `#[AsSeeder]`, which write the sample content of the site. It stops with exit status 1 at the first seeder that throws an exception. A project with no seeder prints how to create one and exits with status 0. See [Seeders](/guide/seeders).

### Discovery Commands

```bash
# List what discovery found, and where it came from
wp foehn discovery:list

# Scan every location and write the cache
wp foehn discovery:generate

# Clear the discovery cache
wp foehn discovery:clear

# Check cache status, per location, and list the classes discovery skipped
wp foehn discovery:status
```

The cache also fills itself on the first request that finds it missing, and `composer install` clears it. See [Discovery Cache](/guide/discovery-cache) for more details on caching, and [Listing what was found](/guide/discovery-cache#listing-what-was-found) for `discovery:list`.

### Rewrite Rules

```bash
# Rebuild the rewrite rules, and forget the hash Foehn compares them against
wp foehn rewrite:flush
```

Foehn flushes on its own when the set of `#[AsRewriteRule]` declarations changes. This command is for when something else left the rules stale. See [Rewrite Rules](/guide/rewrite-rules#flushing-which-is-the-whole-difficulty).

### Verification

```bash
# Fail a CI job when a WordPress or plugin update raised a diagnostic
wp foehn verify --profile=updates --output=build/foehn-verification.json

# Refuse to finish a deploy on unsafe production configuration
wp foehn verify --profile=production
```

Runs one named release gate and writes a deterministic JSON report. It exits `0` on a pass, `1` on actionable diagnostics or a failed check, and `2` when the gate could not run at all — so a CI job can tell a regression from a broken step. The profile is required and there is no flag to select individual checks. See [Verification](/guide/verification) for both profiles, the report schema, and what each gate cannot observe.

### Database Export

```bash
# Write a light, anonymized dump of the database
wp foehn db:export

# Stream it from production to a local file
ssh production 'wp foehn db:export -' > db.sql.gz

# Keep one year of revisions and orders
wp foehn db:export db.sql.gz --from=-1year
```

Writes a gzipped SQL dump to import locally. Users, user meta, comments and WooCommerce orders are anonymized, transients are left out, and revisions and orders older than the window are left out. A table no rule covers is exported without rows and listed at the end. See [Database Export](/guide/database-export) for every rule, the config file, and what is not anonymized.

### Errors

```bash
# The last error, with the request that raised it
wp foehn errors

# The exceptions and fatal errors of the last hour, for an agent
wp foehn errors --level=exception,fatal --since=-1hour --limit=10 --format=json

# Start again from an empty log
wp foehn errors --clear

# The JavaScript errors of the pages the Vite dev server served
wp foehn errors --browser --level=error,rejection --limit=5
```

Prints the last errors the site recorded in the `local` and `development` environments: uncaught exceptions, fatal errors, and the warnings and notices of the theme's own code. Each entry gives the file and line, the Twig template and line, a short stack, the URL, the WordPress template, the controller, the view and its context keys. See [The error log](/guide/troubleshooting#the-error-log). With `--browser`, it prints the console errors and the uncaught errors of the pages that `npm run dev` serves, which `@studiometa/foehn-vite-plugin` writes with Vite 8. See [Browser errors](/guide/troubleshooting#browser-errors).

### Debug Log

```bash
# The last 20 groups of the WordPress debug log
wp foehn log

# The fatal errors and warnings of the last hour, for an agent
wp foehn log --level=fatal,warning --since=-1hour --format=json

# Every entry about one class
wp foehn log --grep=ProductController --limit=5
```

Reads `wp-content/debug.log`, or the file `WP_DEBUG_LOG` names, as entries with their level, message, file and line, and the stack trace of a fatal error. Entries that repeat are one group, with a count and the time of the first and the last. See [The debug log](/guide/troubleshooting#the-debug-log).

### Request Trace

```bash
# Request a page and print what ran
wp foehn trace /about/

# The last request a browser made, or one by the id of its X-Foehn-Trace header
wp foehn trace last
wp foehn trace 3fa2c1 --format=json

# The last requests, one per line
wp foehn trace list --limit=20
```

Prints what a front-end request ran, in the `local` and `development` environments: its status, time and query count, the template files WordPress tried and the one it found, the controller, each Twig view with its context providers and keys, the blocks, and the errors of the request. See [Request Trace](/guide/request-trace).

### Security Keys

WordPress signs authentication cookies and nonces with eight keys. They live in the environment, so a project keeps them wherever it keeps its other secrets. `composer install` fills them into `.env` on a first install, and the generated `wp-config.php` refuses to serve a production request without them.

```bash
# Generate keys for a project that has none
wp foehn salts:generate

# Rotate them — this logs every user out
wp foehn salts:generate --force
```

Rotating replaces the keys the current cookies were signed with, so every session ends.

#### Where the keys come from

`wp-config.php` reads them in this order:

| Source                              | Notes                                                                                                                   |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `config/wordpress-salts.config.php` | Only if a project chooses to use a PHP file. Read first, so it wins.                                                    |
| Environment                         | The default. `.env`, container variables, a VM's environment, a secret pulled from a vault — anything that reaches PHP. |

`.env` is untracked and managed per install; `.env.example` lists the eight names empty, so they are visible without being committed. A value that is empty or still starts with `change-me-` counts as absent.

Because the environment is read, nothing has to end up in a file at all: export the keys from your orchestrator or your vault and the installer leaves them alone. It checks for them before generating.

If you would rather keep them in a PHP file, write one and it takes precedence:

```bash
wp foehn salts:generate --path=config/wordpress-salts.config.php
```

The command warns when that file exists and you rotate `.env`, since the file is what WordPress would still read.

## Custom Commands

Create custom WP-CLI commands with `#[AsCliCommand]`:

```php
<?php
// app/Console/ImportProductsCommand.php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'import:products',
    description: 'Import products from CSV file',
)]
final class ImportProductsCommand
{
    /**
     * Import products from a CSV file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the CSV file
     *
     * [--dry-run]
     * : Preview without importing
     *
     * ## EXAMPLES
     *
     *     wp foehn import:products products.csv
     *     wp foehn import:products products.csv --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assocArgs Named arguments
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $args[0];
        $dryRun = isset($assocArgs['dry-run']);

        if (!file_exists($file)) {
            WP_CLI::error("File not found: {$file}");
        }

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);

            if ($dryRun) {
                WP_CLI::log("Would import: {$data['name']}");
            } else {
                $this->importProduct($data);
                WP_CLI::log("Imported: {$data['name']}");
            }

            $count++;
        }

        fclose($handle);

        WP_CLI::success("Processed {$count} products");
    }

    private function importProduct(array $data): int
    {
        $id = wp_insert_post([
            'post_type' => 'product',
            'post_title' => $data['name'],
            'post_content' => $data['description'] ?? '',
            'post_status' => 'publish',
        ]);

        if (isset($data['price'])) {
            update_post_meta($id, 'price', $data['price']);
        }

        return $id;
    }
}
```

**Usage:**

```bash
wp foehn import:products /path/to/products.csv
wp foehn import:products /path/to/products.csv --dry-run
```

## Command with Progress Bar

```php
<?php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'images:optimize',
    description: 'Optimize all media library images',
)]
final class OptimizeImagesCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $total = count($attachments);

        if ($total === 0) {
            WP_CLI::warning('No images found');
            return;
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Optimizing images', $total);

        foreach ($attachments as $id) {
            $this->optimizeImage($id);
            $progress->tick();
        }

        $progress->finish();
        WP_CLI::success("Optimized {$total} images");
    }

    private function optimizeImage(int $id): void
    {
        // Optimization logic
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata(
            $id,
            get_attached_file($id)
        ));
    }
}
```

## Command with Subcommands

For complex commands, use separate methods:

```php
<?php

namespace App\Console;

use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'cache',
    description: 'Manage application cache',
)]
final class CacheCommand
{
    /**
     * Clear all caches.
     *
     * ## EXAMPLES
     *
     *     wp foehn cache clear
     */
    public function clear(): void
    {
        wp_cache_flush();
        WP_CLI::success('Cache cleared');
    }

    /**
     * Show cache statistics.
     *
     * ## EXAMPLES
     *
     *     wp foehn cache stats
     */
    public function stats(): void
    {
        global $wp_object_cache;

        WP_CLI::log('Cache Statistics:');
        WP_CLI::log('  Hits: ' . ($wp_object_cache->cache_hits ?? 'N/A'));
        WP_CLI::log('  Misses: ' . ($wp_object_cache->cache_misses ?? 'N/A'));
    }

    /**
     * Warm up the cache.
     *
     * ## OPTIONS
     *
     * [--post-types=<types>]
     * : Comma-separated post types to warm
     *
     * ## EXAMPLES
     *
     *     wp foehn cache warm
     *     wp foehn cache warm --post-types=post,page,product
     */
    public function warm(array $args, array $assocArgs): void
    {
        $postTypes = isset($assocArgs['post-types'])
            ? explode(',', $assocArgs['post-types'])
            : ['post', 'page'];

        foreach ($postTypes as $type) {
            $posts = get_posts([
                'post_type' => $type,
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);

            foreach ($posts as $id) {
                get_post($id);
                get_post_meta($id);
            }

            WP_CLI::log("Warmed {$type}: " . count($posts) . ' posts');
        }

        WP_CLI::success('Cache warmed');
    }
}
```

**Usage:**

```bash
wp foehn cache clear
wp foehn cache stats
wp foehn cache warm --post-types=product
```

## Command with Tables

```php
#[AsCliCommand(
    name: 'products:list',
    description: 'List all products',
)]
final class ListProductsCommand
{
    public function __invoke(array $args, array $assocArgs): void
    {
        $products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
        ]);

        if (empty($products)) {
            WP_CLI::warning('No products found');
            return;
        }

        $items = array_map(fn($p) => [
            'ID' => $p->ID,
            'Title' => $p->post_title,
            'Status' => $p->post_status,
            'Price' => get_post_meta($p->ID, 'price', true) ?: 'N/A',
        ], $products);

        WP_CLI\Utils\format_items(
            $assocArgs['format'] ?? 'table',
            $items,
            ['ID', 'Title', 'Status', 'Price']
        );
    }
}
```

## Dependency Injection

Commands support constructor injection:

```php
<?php

namespace App\Console;

use App\Services\ExportService;
use Studiometa\Foehn\Attributes\AsCliCommand;
use WP_CLI;

#[AsCliCommand(
    name: 'export:orders',
    description: 'Export orders to CSV',
)]
final class ExportOrdersCommand
{
    public function __construct(
        private readonly ExportService $export,
    ) {}

    public function __invoke(array $args, array $assocArgs): void
    {
        $file = $this->export->ordersToCSV();
        WP_CLI::success("Exported to: {$file}");
    }
}
```

## Long Description

Provide detailed help with `longDescription`:

```php
#[AsCliCommand(
    name: 'sync:inventory',
    description: 'Sync inventory from external API',
    longDescription: <<<'DOC'
## DESCRIPTION

Synchronizes product inventory levels from the external inventory
management system.

## OPTIONS

[--force]
: Force sync even if recently updated

[--products=<ids>]
: Comma-separated product IDs to sync

## EXAMPLES

    # Sync all products
    wp foehn sync:inventory

    # Force sync specific products
    wp foehn sync:inventory --products=123,456 --force

## NOTES

This command requires API credentials in wp-config.php:
- INVENTORY_API_KEY
- INVENTORY_API_SECRET
DOC,
)]
final class SyncInventoryCommand {}
```

## Attribute Parameters

| Parameter         | Type      | Default    | Description              |
| ----------------- | --------- | ---------- | ------------------------ |
| `name`            | `string`  | _required_ | Command name             |
| `description`     | `string`  | _required_ | Short description        |
| `longDescription` | `?string` | `null`     | Detailed help (docblock) |

## See Also

- [API Reference: #[AsCliCommand]](/api/as-cli-command)
- [WP-CLI Commands Cookbook](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/)
