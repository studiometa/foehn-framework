<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsShortcode;

final class ShortcodeFixture
{
    #[AsShortcode('greeting')]
    public function greeting(array $atts, ?string $content, string $tag): string
    {
        return 'Hello!';
    }

    #[AsShortcode('farewell')]
    public function farewell(array $atts, ?string $content, string $tag): string
    {
        return 'Goodbye!';
    }
}
