<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsCliCommand;

/**
 * Invalid: has #[AsCliCommand] but does NOT implement CliCommandInterface.
 */
#[AsCliCommand(name: 'test:invalid')]
final class InvalidCliCommandFixture {}
