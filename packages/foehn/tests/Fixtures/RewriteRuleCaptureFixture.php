<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use WP;

/**
 * A rule whose every query variable comes out of the URL.
 *
 * There is no constant to compare against, so this is the case that decides
 * whether such a rule can dispatch at all. `GlideRoute` is one.
 */
#[AsRewriteRule(regex: '^_image/(.+)$', query: 'index.php?foehn_image=$matches[1]', queryVars: ['foehn_image'])]
final class RewriteRuleCaptureFixture implements RewriteHandlerInterface
{
    public static int $handled = 0;

    public function handle(WP $wp): void
    {
        self::$handled++;
    }
}
