<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use WP;

#[AsRewriteRule(regex: '^webhook/stripe/?$', query: 'index.php?foehn_route=stripe-webhook', queryVars: ['foehn_route'])]
final class RewriteRuleFixture implements RewriteHandlerInterface
{
    public static int $handled = 0;

    public function handle(WP $wp): void
    {
        self::$handled++;
    }
}
