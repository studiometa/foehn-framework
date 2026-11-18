<?php

declare(strict_types=1);

namespace Tests\Fixtures\PostMeta;

use Studiometa\Foehn\Attributes\AsPostMeta;

#[AsPostMeta(key: 'subtitle', control: 'text')]
final class ControlWithoutPostType {}
