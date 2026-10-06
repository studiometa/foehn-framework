<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsBlockCategory;

/**
 * Repeats a slug from WordPress core and one from BlockCategoryFixture.
 */
#[AsBlockCategory(slug: 'theme', title: 'Theme Blocks')]
#[AsBlockCategory(slug: 'acme-layout', title: 'Other Layout')]
#[AsBlockCategory(slug: 'acme-media', title: 'Media')]
final class BlockCategoryOverlapFixture {}
