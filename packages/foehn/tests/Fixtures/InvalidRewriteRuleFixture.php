<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsRewriteRule;

#[AsRewriteRule(regex: '^x/?$', query: 'index.php?x=1', after: 'sideways')]
final class InvalidRewriteRuleFixture {}
