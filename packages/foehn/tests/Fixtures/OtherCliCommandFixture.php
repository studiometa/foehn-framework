<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;

/**
 * A second class with the command name of CliCommandFixture, for the clash case.
 */
#[AsCliCommand(name: 'test:run', description: 'Run another test command')]
final class OtherCliCommandFixture implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void {}
}
