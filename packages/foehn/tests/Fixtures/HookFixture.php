<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

final class HookFixture
{
    #[AsAction('init')]
    public function onInit(): void
    {
    }

    #[AsAction('wp_head', priority: 5, acceptedArgs: 0)]
    public function onWpHead(): void
    {
    }

    #[AsFilter('the_content')]
    public function filterContent(string $content): string
    {
        return $content;
    }

    #[AsFilter('the_title', priority: 20, acceptedArgs: 2)]
    public function filterTitle(string $title, int $postId): string
    {
        return $title;
    }
}
