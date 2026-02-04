<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsViewComposer;
use Studiometa\WPTempest\Contracts\ViewComposerInterface;

#[AsViewComposer(templates: ['single', 'page'], priority: 5)]
final class ViewComposerFixture implements ViewComposerInterface
{
    public function compose(array $context): array
    {
        return array_merge($context, ['foo' => 'bar']);
    }
}
