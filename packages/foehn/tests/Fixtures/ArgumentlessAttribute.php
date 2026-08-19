<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Attribute;

/**
 * An attribute with no constructor at all.
 *
 * ClassFileGenerator reads an attribute's accepted argument names from its
 * constructor; this pins what it does when there is none to read.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ArgumentlessAttribute {}
