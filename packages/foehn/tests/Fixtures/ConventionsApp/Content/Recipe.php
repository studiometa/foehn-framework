<?php

declare(strict_types=1);

namespace Tests\Fixtures\ConventionsApp\Content;

use Studiometa\Foehn\Attributes\AsPostType;

#[AsPostType(name: 'recipe', hasArchive: true)]
final class Recipe extends BaseContent {}
