<?php

declare(strict_types=1);

namespace Tests\Fixtures\ConventionsApp\Content;

use Studiometa\Foehn\Attributes\AsPostType;

#[AsPostType(name: 'chef')]
final class Chef extends BaseContent {}
