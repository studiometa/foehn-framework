# Request Trace

In the `local` and `development` environments, each front-end request stores what it ran, and `wp foehn trace` prints it: the template files WordPress tried, the one it found, the controller, the Twig views with their context providers and keys, the blocks, the query count, the time and the errors of the request. Use it when a page renders the wrong template, the wrong view or the wrong data.

```bash
wp foehn trace /
```

```
GET / · 200 · 669 ms · 16 queries
  trace 238fc0 at 2026-11-12 14:24:12 UTC
template
  tried front-page.php, home.php, index.php
  found web/wp-content/themes/starter-theme/index.php
  matched front-page · controller App\Controllers\ArchiveController
views
  pages/archive.twig
    providers App\ContextProviders\GlobalContextProvider
    context site, theme, user, http_host, wp_title, body_class, menus, post, posts, pagination, … (+4)
  sections/posts.twig
    providers App\ContextProviders\GlobalContextProvider
    context site, theme, user, http_host, wp_title, body_class, menus, post, posts, pagination, … (+4)
blocks
  core/paragraph
Every field: wp foehn trace 238fc0 --format=json.
```

## The command

| Argument or option | Effect                                                                                                           |
| ------------------ | ---------------------------------------------------------------------------------------------------------------- |
| `<path>`, `<url>`  | Request this page of the site, then print its trace: `/about/`, or a URL of the site                             |
| `last`             | The trace of the last traced request, such as the page a browser loaded. The default                             |
| `<id>`             | The trace of one request: the 6 characters of its `X-Foehn-Trace` response header                                |
| `list`             | The last traced requests, one per line: id, time, status, duration, method and URL. `--limit=<n>`, 10 by default |
| `--format=json`    | Every field of the trace, and the full error entries of the request, as JSON on one line                         |
| `--clear`          | Delete every trace                                                                                               |

With a path, the command makes an HTTP request to the site with `wp_remote_get()`, as an anonymous visitor: no cookies, and no redirects followed. The trace is the one of a real request, through the web server. A redirect is traced with its status and the URL it sends to. The command only requests URLs of the site, and it does not check the certificate, because a local site often has one that WordPress does not know.

The text cuts each list after 10 items and shows 8 views and 5 errors. `--format=json` gives every field. `wp foehn errors --request=<id>` gives the errors of the request in full.

## What a trace holds

| Field           | Content                                                                                                                                                                                                                           |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`            | 6 hexadecimal characters, sent in the `X-Foehn-Trace` response header                                                                                                                                                             |
| `time`          | When the request ended, UTC, ISO 8601                                                                                                                                                                                             |
| `method`, `url` | The HTTP method and the request URI, cut after 2000 characters                                                                                                                                                                    |
| `status`        | The HTTP status of the response                                                                                                                                                                                                   |
| `location`      | The `Location` header of a redirect, or `null`                                                                                                                                                                                    |
| `durationMs`    | The time from the start of the request to its end, in milliseconds                                                                                                                                                                |
| `queries`       | The number of database queries (`get_num_queries()`)                                                                                                                                                                              |
| `hierarchy`     | The template files WordPress tried, in order (the `{type}_template_hierarchy` filters)                                                                                                                                            |
| `templateFile`  | The template file WordPress found, before a template controller replaced it                                                                                                                                                       |
| `template`      | The WordPress template Føhn matched, such as `single-product`                                                                                                                                                                     |
| `controller`    | The template controller that handles it, or `null`                                                                                                                                                                                |
| `views`         | Each Twig view Timber rendered, in order: `view`, `count`, `keys` (the context keys, up to 40), `candidates` (the views `renderFirst()` tried, when it chose this one) and `providers` (the context providers that ran, in order) |
| `blocks`        | Each block name rendered, with the number of times                                                                                                                                                                                |
| `errors`        | In the output of the command only: the entries of the [error log](/guide/troubleshooting#the-error-log) with this `requestId`                                                                                                     |

Each list keeps at most 30 items.

## Which requests are traced

A request is traced when WordPress sends the headers of a front-end page (the `send_headers` action of `WP::main()`). The admin, `admin-ajax.php`, WP-Cron, REST requests and WP-CLI do not send it, so they are not traced. `/favicon.ico` and `/robots.txt`, which a browser asks for after a page, get an id but no stored trace, so `last` stays the page. A file the web server serves and a page the page cache serves run no PHP, so they have no trace either: the command says so, and gives the `wp foehn cache:clear --url=` command to run.

The id is given when the headers are sent. An error raised before, during `init` for example, has no `requestId`. An error raised after has the id, and the trace lists it.

The store is `wp-content/cache/foehn/traces`, one JSON file per request. It keeps the last 100 traces and deletes the older ones.

## When requests are traced

`FoehnConfig::$traceRequests` decides. With the default, `null`, requests are traced when the environment (`WP_ENVIRONMENT_TYPE`) is `local` or `development`. Set it to `true` to trace them in another environment, such as a staging site, and to `false` to never trace them:

```php
// app/foehn.config.php
return new FoehnConfig(
    traceRequests: true,
);
```

Each traced request writes a file and sends a header. A trace holds URLs, template paths and class names, and the cache directory can be served by the web server: do not turn tracing on where people you do not trust can read the files of `wp-content`. The page cache does not store the `X-Foehn-Trace` header.

## Related

- [Problems and Errors](/guide/troubleshooting)
- [Template Controllers](/guide/template-controllers)
- [Context Providers](/guide/context-providers)
- [CLI Commands](/guide/cli-commands#request-trace)
