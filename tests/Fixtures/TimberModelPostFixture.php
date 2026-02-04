<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsTimberModel;
use Timber\Post;

#[AsTimberModel(name: 'post')]
final class TimberModelPostFixture extends Post {}
