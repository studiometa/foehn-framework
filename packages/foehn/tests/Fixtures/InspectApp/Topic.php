<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Term;

/**
 * A model for core `category`, which `product` names among its taxonomies.
 */
#[AsTimberModel('category')]
final class Topic extends Term {}
