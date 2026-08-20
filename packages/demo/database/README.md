# The demo site, in a box

Everything needed to stand the portfolio up on a fresh WordPress, so nobody has to
recreate six projects and thirty photographs by hand.

```bash
ddev start
./database/restore.sh
```

## What is here

| File                | What it holds                                                      |
| ------------------- | ------------------------------------------------------------------ |
| `demo.sql.gz`       | Posts, meta, options, menus, settings — the whole database, ~23 KB |
| `media/`            | The 30 photographs as originals, plus `credits.json`               |
| `seed.php`          | Builds the same site from nothing. Idempotent                      |
| `restore-media.php` | Puts the photographs back where the database says they are         |
| `restore.sh`        | The three steps, in order                                          |
| `fetch-media.py`    | How `media/` was produced, so the set is reproducible              |

## Why three steps and not one

A WordPress site is not only its database.

**The database** is `demo.sql.gz`, and `ddev import-db` is enough for it.

**The photographs are not in it.** The demo offloads uploads to object storage, so
the files live in a MinIO bucket that no repository should contain. The originals
travel as plain JPEGs instead, and `restore-media.php` copies each one to the path
its attachment already claims — `get_attached_file()` returns an `s3://` path while
`humanmade/s3-uploads` is active, so the copy goes through the plugin's own stream
wrapper and lands in the bucket. The site then serves them from its own domain:
`.ddev/nginx/uploads-proxy.conf` maps `/wp-content/uploads/` to the bucket, so no
bucket hostname appears in any page. Sub-sizes are never shipped: they are rebuilt from
the original, which is also what proves the original arrived.

**Rewrite rules are not in it either.** They are derived from what the theme
registers, and registered rules do nothing until WordPress flushes them once. A
freshly imported database carries whatever the dump's site had, so `restore.sh`
flushes.

## Rebuilding rather than restoring

```bash
ddev exec 'cd /var/www/html && wp site empty --yes'
ddev exec 'cd /var/www/html && wp eval-file database/seed.php'
ddev exec 'cd /var/www/html && wp foehn rewrite:flush'
```

Slower, and the result is the same site — `demo.sql.gz` is a dump of a site seeded
exactly this way. `restore.sh` falls back to it when the dump is missing.

## The photographs

Thirty images from a single Unsplash collection,
[Minimal BW](https://unsplash.com/collections/2393384/minimal-bw), curated into six
series. One collection on purpose: a portfolio has to look like one body of work,
and the Swiss layout depends on the images sharing a treatment.

`credits.json` carries the photographer's name and profile URL for every file.
`seed.php` writes both onto the attachment as post meta, and
`templates/components/photograph.twig` prints them under every plate. That is a
licence requirement, not decoration — the smoke test asserts that the number of
credits on a project page equals the number of photographs, so a template that drops
them fails CI.

To refresh or change the set, `fetch-media.py` takes an Unsplash access key in
`UNSPLASH_ACCESS_KEY` and rewrites `media/`. It triggers each photo's
`download_location`, which the Unsplash API terms require. No key is stored here.
