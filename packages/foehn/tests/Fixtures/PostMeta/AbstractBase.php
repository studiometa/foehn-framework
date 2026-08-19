<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Timber\Post;

/**
 * A base a theme's models extend. Its declarations belong to them, not to it.
 */
#[AsPostMeta(key: 'inherited', type: 'string')]
abstract class AbstractBase extends Post {}
