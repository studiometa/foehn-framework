<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(name: 'gallery', singular: 'Gallery', plural: 'Galleries')]
#[AsPostMeta(key: 'credits', type: 'array', schema: ['items' => ['type' => 'string']])]
#[AsPostMeta(key: 'raw', type: 'array', showInRest: false)]
final class Gallery extends Post {}
