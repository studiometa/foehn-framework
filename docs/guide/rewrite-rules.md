# Rewrite Rules

A webhook, a form handler, a signed download URL — anything the theme answers at a URL of its own. `#[AsRewriteRule]` declares the URL and the class answers it.

Written without one, this is a `template_redirect` hook reading `$_SERVER['REQUEST_URI']`, which is the thing rewrite rules exist to avoid.

## One class, both halves

```php
<?php
// app/Routes/StripeWebhook.php

namespace App\Routes;

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use WP;

#[AsRewriteRule(
    regex: '^webhook/stripe/?$',
    query: 'index.php?foehn_route=stripe-webhook',
    queryVars: ['foehn_route'],
)]
final readonly class StripeWebhook implements RewriteHandlerInterface
{
    public function handle(WP $wp): void
    {
        $payload = file_get_contents('php://input');

        // …verify the signature, do the work…

        status_header(200);
        exit;
    }
}
```

`handle()` runs on `parse_request`, before WordPress runs the main query — which is what lets a webhook answer and `exit` without a page being rendered first. A handler that only prepares something can return instead, and WordPress carries on.

## Handling is optional

A rule that rewrites onto something WordPress already renders needs no interface:

```php
#[AsRewriteRule(
    regex: '^brochure/([^/]+)/?$',
    query: 'index.php?post_type=brochure&name=$matches[1]',
    after: 'bottom',
)]
final class BrochurePermalink {}
```

## Parameters

| Parameter   | Default    | Description                                                                            |
| ----------- | ---------- | -------------------------------------------------------------------------------------- |
| `regex`     | _required_ | The pattern matched against the path, e.g. `^webhook/stripe/?$`                        |
| `query`     | _required_ | What it rewrites to. `$matches[1]` and its siblings carry the pattern's capture groups |
| `queryVars` | `[]`       | Query variables to register. WordPress **discards any variable it does not know**      |
| `after`     | `'top'`    | `'top'` matches before WordPress's own rules, `'bottom'` after them                    |

`queryVars` is not optional in practice: a rule rewriting to `index.php?foehn_route=x` reaches a request with no `foehn_route` in it until the variable is registered, and the handler is never called.

`after` defaults to `'top'` because that is what a webhook wants. A rule at the bottom is reached only once every built-in pattern has failed to match.

## Flushing, which is the whole difficulty

Rules registered in code do nothing until WordPress flushes them once, and `flush_rewrite_rules()` rewrites an option — calling it on every request is a well-known way to ruin a site.

Føhn hashes the declared rule set, keeps it in `foehn_rewrite_rules_hash`, and flushes exactly when the hash changes. Add a rule, reload, it works. Nothing to remember, and no flush on a request that did not need one.

The hash covers the patterns, not the classes: moving a rule to another class changes what answers the URL, not what WordPress has to match. It is order-independent too, since discovery order is filesystem order.

When something else has left the rules stale — a plugin that flushed over them, a database restored from elsewhere:

```bash
wp foehn rewrite:flush
```

::: warning
**Plain permalinks bypass rewrite rules entirely.** On a site whose permalink structure is the default, no rule will ever match and no flush will change that. Choose another structure under Settings → Permalinks. `wp foehn rewrite:flush` says so if it finds one.
:::

## Dispatching, and how a request is recognised

The query string a rule rewrites to is what identifies it. `index.php?foehn_route=stripe-webhook` means: the request whose `foehn_route` is `stripe-webhook`. Føhn parses that once, during discovery, and compares it on `parse_request`.

Values that come from the pattern are skipped — `name=$matches[1]` carries whatever was captured, so there is nothing to compare it against. A rule whose query sets nothing fixed therefore has nothing to dispatch on, and its class is never called; give it a query variable of its own.

## Related

- [#[AsRestRoute]](/api/as-rest-route) — for a JSON API with schema validation and permissions, which is most of what people reach for a route for
- [Template Controllers](/guide/template-controllers) — for a URL WordPress already routes
- [CLI Commands](/guide/cli-commands)
