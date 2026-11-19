# Scheduled Blocks

An editor can show any block only between two dates, for example a seasonal priority on the home page. This works on every block, core blocks included, with nothing to declare in the theme.

## In the editor

Select a block and open the **Schedule** panel in the block inspector. It has two fields:

| Field         | Attribute      | Empty value         |
| ------------- | -------------- | ------------------- |
| Visible from  | `visibleFrom`  | Visible now         |
| Visible until | `visibleUntil` | Visible with no end |

Each value is a date and time with no time zone, such as `2026-12-01T09:00`. Føhn reads it in the site time zone (**Settings → General → Timezone**). The block shows from `visibleFrom` and hides from `visibleUntil` on. An invalid value is no limit.

The dates apply to the front end only. In the editor, in the admin and in REST requests, the block renders as usual, so an editor can still see and change it.

## How it works

`Studiometa\Foehn\Blocks\BlockSchedule` is always on. It adds:

- the `visibleFrom` and `visibleUntil` attributes to every block type, with the `register_block_type_args` filter;
- a `render_block` filter that returns an empty string for a block outside its dates;
- the "Schedule" panel script, `wp-content/foehn/schedule.js`, on `enqueue_block_editor_assets`. `composer install` copies it into the web root, as it copies the [block registrar](./block-editor#where-the-registrar-comes-from). Without the file, the dates still apply but the panel is not shown. With `debug` on, a notice gives the missing path.

A hidden block renders nothing, its inner blocks included.

## With the page cache

A stored page does not change when a date passes, because no content edit happens. Føhn keeps the [page cache](./page-cache) correct with two parts:

1. When the page cache stores a page that renders a scheduled block, Føhn records the URL of the page and the next date at which the output of one of its blocks changes. Each URL has its own non-autoloaded option, `foehn_page_cache_schedule_<md5 of the URL>`, and keeps its earliest date. A response that the page cache does not store (a 404 with `cacheNotFound` off, a redirect, an incomplete page) records nothing.
2. `PageCache\ScheduledPurge` runs on the `foehn_cron_heartbeat` WordPress cron event and deletes the stored page of each URL whose date has passed. The next visit renders the page again and records its next date, if it has one.

The heartbeat event is a WordPress cron event, not an Action Scheduler job, so the purge works without Action Scheduler. The real cron runs it on each pass (`wp cron event run --due-now`, see [The real cron](./verification#the-real-cron)). So the maximum delay between a date and the change on a cached page is one step of the real cron. The Docker image runs the cron every 15 minutes by default: a block scheduled at 09:00 can show on a cached page at up to 09:15. Set a shorter cron step when the delay must be shorter.

## Out of scope

Time zones other than the site time zone, and recurring dates (for example "every weekend").

## See Also

- [Block Editor](./block-editor)
- [Static Page Cache](./page-cache)
- [Cron Jobs](./cron)
