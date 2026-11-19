<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(name: 'event', singular: 'Event', plural: 'Events')]
#[AsPostMeta(key: 'tags', control: 'text', single: false)]
final class RepeatableControl extends Post {}
