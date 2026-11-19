<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Post;

/**
 * A second model on the `event` post type, which declares its own fields.
 */
#[AsTimberModel(name: 'event')]
#[AsPostMeta(key: 'venue', control: 'text', label: 'Venue')]
final class EventVenue extends Post {}
