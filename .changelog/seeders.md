### Added

- Seeders: a class with `#[AsSeeder(order:, description:)]` that implements `SeederInterface` writes the sample content of a site in `run()`. `wp foehn seed` runs every seeder by `order` (then by class name), `--class=<name>` runs one, and `--dry-run` lists them. The command stops with exit status 1 at the first seeder that throws. A seeder must be idempotent. See [Seeders](https://studiometa.github.io/foehn-framework/guide/seeders)
- `Upsert::post($type, $slug, $data, $meta)` updates the post of this type that has this slug, or creates it, so a seeder can run again without duplicating content
- `wp foehn make:seeder <name>` generates a seeder in `app/Seeders/`, with `--order`, `--description`, `--dry-run` and `--format=json`
- `wp foehn inspect` has a `seeders` section with the class, order and description of each seeder, in run order
- The starter `AGENTS.md` tells coding agents to put sample content and menus in a seeder and run `wp foehn seed`, never to create them only in the database. A project made from an earlier starter can copy the section from `packages/starter/AGENTS.md`

### Changed

- The demo writes its portfolio with six seeders in `theme/app/Seeders/` instead of `database/seed.php`. `database/restore.sh` and the Fly entrypoint run `wp foehn seed`
