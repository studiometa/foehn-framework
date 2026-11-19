<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Search;

use InvalidArgumentException;

/**
 * A place search request that breaks the request contract: answered with `400`.
 */
final class InvalidPlaceSearch extends InvalidArgumentException {}
