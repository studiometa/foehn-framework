<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsTimberModel;
use Timber\Term;

#[AsTimberModel(name: 'category')]
final class TimberModelTermFixture extends Term {}
