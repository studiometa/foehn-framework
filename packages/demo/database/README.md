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
| `fix-urls.php`      | Moves the site onto the URL this ddev project serves. Idempotent   |
| `restore.sh`        | The steps, in order                                                |
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

## The URL is not the dump's

A dump remembers the host it was taken on — `foehn-demo.ddev.site` for the one here.
A project renamed, or a ddev with a router domain of its own, is served somewhere
else entirely, and every guid, menu item and serialized option still names the old
host. `restore.sh` moves both halves onto `DDEV_PRIMARY_URL`, which is the only
answer the container itself can give:

`.env` first, because wp-config defines `WP_HOME` from it and a constant beats the
stored `home` option — so no amount of rewriting the database moves a site whose
`.env` still points elsewhere. `WP_SITEURL` is written as `${WP_HOME}/wp` and
follows on its own.

The database second, with `fix-urls.php`. It reads the old host out of the `siteurl`
row rather than through `get_option()`, which would hand back the constant, and
replaces the origin — scheme, host, port — across all tables. `--precise` keeps
serialized values intact. Then it clears the page cache, since pages cached under the
old host would keep serving its URLs.

To move a site without restoring it again:

```bash
ddev exec 'cd /var/www/html && wp eval-file database/fix-urls.php'
```

It changes nothing when there is nothing to move, and takes an explicit URL as an
argument for the cases where `DDEV_PRIMARY_URL` is not the answer.

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
