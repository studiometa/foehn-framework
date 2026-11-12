<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use WP;

#[AsRewriteRule(regex: '^webhook/stripe/?$', query: 'index.php?foehn_route=stripe', queryVars: ['foehn_route'])]
final class StripeWebhook implements RewriteHandlerInterface
{
    public function handle(WP $wp): void {}
}
