<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlockCategory;

#[AsBlockCategory(slug: 'acme-layout', title: 'Layout', icon: 'layout')]
#[AsBlockCategory(slug: 'acme-content', title: 'Content')]
final class BlockCategoryFixture {}
