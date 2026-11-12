# Database Export

`wp foehn db:export` writes a light, anonymized copy of the production database, to import on a local copy of the site. Run it where the data is, and import the file locally.

```bash
# Stream it from production to a local file
ssh production 'cd /path/to/site && wp foehn db:export -' > db.sql.gz

# Import it into ddev
ddev import-db --file=db.sql.gz
```

The command needs no `mysqldump` binary. It reads the database with PHP (PDO), in one transaction, so the site stays online and the dump is consistent.

## Options

| Option          | Rule                                                                                                                                       |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `<file>`        | Where to write the dump. `-` writes it to standard output. The default is `<database>-<date>.sql.gz` in the current directory.             |
| `--from=<date>` | The start of the window for revisions and orders: a date (`2026-01-01`) or a relative date (`-2 weeks`). The default is the config window. |

The dump is always gzipped. Progress and the report go to standard error, so `-` gives a clean stream.

## What the export does

Each table gets one treatment. The command prints the treatment of each table while it runs.

### WordPress core tables

| Table                                         | Treatment                                                                                                                                                                                       |
| --------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `posts`                                       | All rows, except revisions older than the window.                                                                                                                                               |
| `postmeta`, `term_relationships`              | All rows, except the rows of the revisions left out.                                                                                                                                            |
| `terms`, `term_taxonomy`, `termmeta`, `links` | All rows.                                                                                                                                                                                       |
| `options`                                     | All rows, except transients.                                                                                                                                                                    |
| `users`                                       | Anonymized. The email becomes `user-<ID>@example.invalid`, the login and the nicename `user-<ID>`, the display name `User <ID>`. The URL, the password hash and the activation key are emptied. |
| `usermeta`                                    | All rows, except `first_name`, `last_name`, `nickname`, `description`, `session_tokens` and every `billing_*` and `shipping_*` key. Roles and capabilities are kept.                            |
| `comments`                                    | Spam and trash are left out. The author name, email, URL, IP address and user agent are replaced. The comment text is kept.                                                                     |
| `commentmeta`                                 | All rows, except those of spam and trash.                                                                                                                                                       |

`example.invalid` is a reserved domain: no email sent locally can reach anybody. An IP address becomes `192.0.2.1`, a reserved documentation address. Every replacement is deterministic: two exports of the same database give the same values.

Nobody can log in with an exported account, because no password hash leaves production. Set a password locally:

```bash
ddev wp user update user-1 --user_pass=password
```

### Plugin presets

A preset applies only when one of its tables exists.

| Preset           | Treatment                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ---------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| WooCommerce      | `wc_orders`: orders created in the window only, with the billing email, IP address, user agent and customer note replaced. Their addresses, meta, items and item meta follow them. Addresses lose the name, company, street, postcode, email and phone. `wc_customer_lookup` is anonymized. Address, email, IP and browser meta keys (`_billing_*`, `_shipping_*`, `_customer_ip_address`, `_customer_user_agent`) are removed from `postmeta` and `wc_orders_meta`. Tax, shipping and attribute tables are kept. Action Scheduler, sessions, API keys, payment tokens, webhooks, the download log and the analytics tables are exported as structure only: WooCommerce rebuilds the analytics. |
| Gravity Forms    | Forms are kept. Entries, entry meta, entry notes and drafts are structure only.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| Logs and indexes | Structure only: Redirection's 404 and redirect logs, Relevanssi's index and log, Yoast's indexables and links (rebuilt by `wp yoast index`), Wordfence's tables (`wf*`), Simple History. Redirection's redirects and Yoast's primary terms are kept.                                                                                                                                                                                                                                                                                                                                                                                                                                            |

WooCommerce orders in the legacy storage (posts of type `shop_order`) are kept whatever their date. Only their address meta is removed.

### Every other table: structure only

A table that no rule covers is exported **without rows**: the table exists locally, empty. This is fail-closed. A plugin you add tomorrow cannot leak its data into an export because nobody wrote a rule for it.

The command lists these tables at the end:

```
Warning: No rule covers 2 tables, so they were exported without rows: wp_newsletter_subscribers, wp_store_locations. Declare them in app/db-export.config.php.
```

Only the tables with the WordPress table prefix are exported. A database that also holds other applications, or other sites with another prefix, keeps them out of the export.

## Declaring your tables

Tell the export what your own tables hold in `app/db-export.config.php`. Table names are given without the table prefix, and a trailing `*` matches every table that starts with the rest.

```php
<?php
// app/db-export.config.php

use Studiometa\Foehn\Config\DatabaseExportConfig;
use Studiometa\Foehn\DatabaseExport\Anonymizer;

return new DatabaseExportConfig(
    // Exported with all their rows: nothing personal in them.
    safeTables: ['store_locations', 'my_plugin_settings_*'],

    // Exported without rows.
    structureOnlyTables: ['newsletter_subscribers'],

    // Exported with their rows, these columns replaced.
    columns: [
        'event_registrations' => [
            'email' => Anonymizer::Email,
            'full_name' => Anonymizer::Name,
            'phone' => Anonymizer::Empty,
        ],
    ],

    // People of these domains keep their real email, login and name.
    allowedEmailDomains: ['example.com'],

    // How far back revisions and orders go.
    window: '-2 months',
);
```

| Argument              | Default       | Rule                                                                                                                                                                     |
| --------------------- | ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `window`              | `'-2 months'` | A relative date. `--from` replaces it for one export.                                                                                                                    |
| `safeTables`          | `[]`          | All rows. A built-in rule on the same table still applies: declaring `users` safe does not stop its anonymization.                                                       |
| `structureOnlyTables` | `[]`          | No rows. This wins over every other rule, the built-in ones included.                                                                                                    |
| `columns`             | `[]`          | Columns to replace, by table. Added to the built-in rule when there is one.                                                                                              |
| `allowedEmailDomains` | `[]`          | Exact domains, case-insensitive. A user, a comment or an order whose email has one of these domains keeps its email, login and name. Its password hash is still removed. |

A project config can make a rule stricter, never weaker.

The anonymizers are named, not closures, so the config file stays plain data:

| Anonymizer             | Writes                         |
| ---------------------- | ------------------------------ |
| `Anonymizer::Email`    | `user-<token>@example.invalid` |
| `Anonymizer::Username` | `user-<token>`                 |
| `Anonymizer::Name`     | `User <token>`                 |
| `Anonymizer::Ip`       | `192.0.2.1`                    |
| `Anonymizer::Empty`    | An empty string                |

In a project table, `<token>` is the first 12 characters of the SHA-256 hash of the value, so equal values stay equal across rows. `NULL` and an empty string are kept.

## What is not anonymized

Read this before you send an export to somebody outside the team.

- **Post content.** Posts, pages and their meta are kept as they are. A page, a form plugin that stores messages as posts, or a custom field can hold names, emails or addresses.
- **Options.** Every option except transients is kept: the `admin_email` option, and plugin settings, which can hold API keys and SMTP passwords.
- **Comment text** and **order item names**.
- **Legacy WooCommerce orders**, apart from their address meta.
- **Your own tables**, once you declare them safe. Until you declare them, they have no rows.
- **Multisite.** The tables of the sites other than the main one (`wp_2_posts`, …) have no rule and are exported as structure only.

## Importing into ddev

```bash
ddev import-db --file=db.sql.gz
ddev wp search-replace 'https://www.example.com' 'https://my-project.ddev.site' --all-tables
ddev wp user update user-1 --user_pass=password
ddev wp foehn cache:clear
```

`ddev import-db` empties the local database before it imports the file. Change the first URL to the production URL of the site.
