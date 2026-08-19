# #[AsRewriteRule]

Registers a WordPress rewrite rule, and the class that answers it.

## Signature

```php
<?php

namespace Studiometa\Foehn\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsRewriteRule
{
    public function __construct(
        public string $regex,
        public string $query,
        public array $queryVars = [],
        public string $after = 'top',
    ) {}
}
```

## Parameters

| Parameter   | Type           | Default    | Description                                                                            |
| ----------- | -------------- | ---------- | -------------------------------------------------------------------------------------- |
| `regex`     | `string`       | _required_ | The pattern matched against the path                                                   |
| `query`     | `string`       | _required_ | What it rewrites to. `$matches[1]` and its siblings carry the pattern's capture groups |
| `queryVars` | `list<string>` | `[]`       | Query variables to register through the `query_vars` filter                            |
| `after`     | `string`       | `'top'`    | `'top'` or `'bottom'`. Anything else is refused during discovery                       |

## RewriteHandlerInterface

```php
<?php

namespace Studiometa\Foehn\Contracts;

use WP;

interface RewriteHandlerInterface
{
    public function handle(WP $wp): void;
}
```

Optional. Implement it to answer the URL; a rule that rewrites onto an existing template does not need it.

`handle()` is called on `parse_request`, before the main query runs, with the `WP` environment whose `query_vars` hold what the rule's query string set. The instance is resolved from the container, so its constructor is autowired.

## Usage

```php
#[AsRewriteRule(
    regex: '^webhook/stripe/?$',
    query: 'index.php?foehn_route=stripe-webhook',
    queryVars: ['foehn_route'],
)]
final readonly class StripeWebhook implements RewriteHandlerInterface
{
    public function handle(WP $wp): void
    {
        // …
        exit;
    }
}
```

## Notes

- **Registered on `init`**, which is where `add_rewrite_rule()` belongs.
- **Flushed when the rule set changes**, and not otherwise: the declared rules are hashed into `foehn_rewrite_rules_hash`. `wp foehn rewrite:flush` forces it.
- **Plain permalinks bypass rewrite rules entirely.** No rule matches on a site using the default structure.
- **A query variable WordPress does not know is discarded**, so `queryVars` is what makes the rewrite's own variables readable — and what makes the handler reachable.

## Related

- [Guide: Rewrite Rules](/guide/rewrite-rules)
- [#[AsRestRoute]](./as-rest-route)
- [#[AsTemplateController]](./as-template-controller)
