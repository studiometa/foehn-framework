<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsRewriteRule;

/**
 * A rule that only rewrites onto an existing template, so it needs no handler.
 */
#[AsRewriteRule(
    regex: '^brochure/([^/]+)/?$',
    query: 'index.php?post_type=brochure&name=$matches[1]',
    queryVars: [],
    after: 'bottom',
)]
final class RewriteRuleWithoutHandlerFixture {}
