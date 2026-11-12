# Seeders

A seeder is a class that writes the sample content of a site: posts, pages, photographs, menus and the options a site needs to show them. `wp foehn seed` runs the seeders, so a developer or a coding agent can build the site again from an empty database.

Sample content goes in a seeder, never only in the database. Content created with one-off `wp post create`, `wp menu` or `wp option update` commands is lost when the database is emptied or replaced by another dump.

## Create a seeder

```bash
wp foehn make:seeder events --description="Sample events" --dry-run --format=json
wp foehn make:seeder events --description="Sample events"
wp foehn discovery:clear
```

The command writes `app/Seeders/EventsSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

#[AsSeeder(order: 10, description: 'Sample events')]
final class EventsSeeder implements SeederInterface
{
    public function run(): void
    {
        Upsert::post('event', 'spring-fair', [
            'post_title' => 'Spring fair',
            'post_content' => '<p>Stalls, music and food in the main square.</p>',
        ], [
            'starts_at' => '2026-04-18',
        ]);
    }
}
```

A seeder has the `#[AsSeeder]` attribute and implements `SeederInterface`. A class with the attribute and without the interface is not discovered. A seeder registers nothing with WordPress: it runs only when `wp foehn seed` runs it.

| Argument      | Default | Description                                                                 |
| ------------- | ------- | --------------------------------------------------------------------------- |
| `order`       | `10`    | Run order. Lower runs first. Seeders with the same order run by class name. |
| `description` | `''`    | What the seeder writes. `wp foehn inspect` and `wp foehn seed` show it.     |

## Run the seeders

```bash
wp foehn seed                       # every seeder, by order
wp foehn seed --class=EventsSeeder  # one seeder, by short or full class name
wp foehn seed --dry-run             # list the seeders, in order, and run nothing
wp foehn inspect seeders            # the seeders, with their order and description
```

The command stops with exit status 1 at the first seeder that throws an exception, and prints the class and the message. Throw an exception when a seeder cannot do its work, for example when the page it needs does not exist.

When two seeders have the same short class name, `--class` refuses the short name and lists the full names.

## Idempotency

A seeder must be idempotent: a second run updates the content of the first run and creates nothing. `wp foehn seed` can then run on any site, including a site that already has the content.

- Find a post by type and slug before you create it. `Upsert::post($type, $slug, $data, $meta)` does this: it updates the post of this type that has this slug (in any status), or creates it with the `publish` status. It returns the post ID and throws a `RuntimeException` when WordPress refuses the post.
- Find an attachment by a meta value that you write on it, such as an external ID, before you import the file again.
- Find a menu by name, and write its items again: delete the old items, then add the new ones.
- Write options with `update_option()`, which replaces the value.

Run `wp foehn seed` two times and count the posts after each run. The counts must not change.

## Order

Use `order` when a seeder reads what another seeder writes. A seeder finds that content again, by slug or by meta, and does not receive it from the other seeder. So each seeder can also run alone with `--class`, on a site that has the content of the seeders before it.

The [demo](/guide/demo) writes its whole portfolio with six seeders in `theme/app/Seeders/`:

| Order | Seeder              | Writes                                                  |
| ----- | ------------------- | ------------------------------------------------------- |
| 10    | `MediaSeeder`       | The photographs, found again by their Unsplash ID       |
| 20    | `ProjectSeeder`     | The projects, with the photographs of each as a gallery |
| 30    | `PageSeeder`        | The Home and About pages                                |
| 40    | `TestimonialSeeder` | The testimonials                                        |
| 50    | `SettingsSeeder`    | The site options, with the Home page as the front page  |
| 60    | `MenuSeeder`        | The menus, with a link to the About page                |

## See Also

- [CLI Commands](/guide/cli-commands)
- [AI Agents](/guide/ai-agents)
- [Demo Project](/guide/demo)
