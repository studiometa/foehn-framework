# #[AsSeeder]

Mark a class as a seeder: code that writes the sample content of a site. `wp foehn seed` runs it.

## Signature

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSeeder
{
    public function __construct(
        public int $order = 10,
        public string $description = '',
    ) {}
}
```

## Parameters

| Parameter     | Type     | Default | Description                                                                 |
| ------------- | -------- | ------- | --------------------------------------------------------------------------- |
| `order`       | `int`    | `10`    | Run order. Lower runs first. Seeders with the same order run by class name. |
| `description` | `string` | `''`    | What the seeder writes. `wp foehn inspect` and `wp foehn seed` show it.     |

## SeederInterface

The class must implement `Studiometa\Foehn\Contracts\SeederInterface`:

```php
interface SeederInterface
{
    public function run(): void;
}
```

`run()` must be idempotent: a second run updates the content of the first run and creates nothing. Throw an exception to stop `wp foehn seed` with an error.

A class with `#[AsSeeder]` and without the interface is skipped. `wp foehn inspect problems` reports it. A seeder registers nothing with WordPress.

## Upsert::post()

`Studiometa\Foehn\Seeders\Upsert::post()` writes a post that a second run finds again:

```php
public static function post(string $type, string $slug, array $data = [], array $meta = []): int
```

| Parameter | Type                   | Description                                                  |
| --------- | ---------------------- | ------------------------------------------------------------ |
| `$type`   | `string`               | The post type                                                |
| `$slug`   | `string`               | The post name. A second run finds the post by type and slug. |
| `$data`   | `array<string, mixed>` | Fields for `wp_insert_post()`, such as `post_title`          |
| `$meta`   | `array<string, mixed>` | Meta key and value pairs, written with `update_post_meta()`  |

It updates the post of this type with this slug, in any status, or creates it with the `publish` status. It returns the post ID. It throws a `RuntimeException` when WordPress refuses the post.

## Usage

```php
<?php

namespace App\Seeders;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Seeders\Upsert;

#[AsSeeder(order: 20, description: 'Sample events')]
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

```bash
wp foehn seed                       # every seeder, by order
wp foehn seed --class=EventsSeeder  # one seeder
wp foehn seed --dry-run             # list the seeders and run nothing
```

## Related

- [Guide: Seeders](/guide/seeders)
- [CLI Commands](/guide/cli-commands)
