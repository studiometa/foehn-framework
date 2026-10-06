<?php

declare(strict_types=1);

namespace Demo\Blocks;

use Studiometa\Foehn\Attributes\AsBlockCategory;

/**
 * The block categories the demo adds to the inserter.
 *
 * WordPress core has no `layout` category, and the hero block is filed under
 * it: without this class the editor puts the hero under "Uncategorized". The
 * class needs no body — the attribute is the whole declaration.
 */
#[AsBlockCategory(slug: 'layout', title: 'Layout', icon: 'layout')]
final class BlockCategories {}
