<?php

declare(strict_types=1);

namespace Tests\Fixtures\Bindings;

use Studiometa\Foehn\Attributes\AsBlockBinding;
use Studiometa\Foehn\Contracts\BlockBindingInterface;
use WP_Block;

#[AsBlockBinding(name: 'theme/reading-time', label: 'Reading time', usesContext: ['postId'])]
final class ReadingTimeFixture implements BlockBindingInterface
{
    /** @var list<array{args: array<string, mixed>, attribute: string, postId: mixed}> */
    public static array $calls = [];

    public function value(array $args, WP_Block $block, string $attribute): ?string
    {
        self::$calls[] = ['args' => $args, 'attribute' => $attribute, 'postId' => $block->context['postId'] ?? null];

        if ($attribute === 'alt') {
            return null;
        }

        return '4 minutes';
    }
}
