<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;

#[AsCliCommand(
    name: 'test:run',
    description: 'Run a test command',
    longDescription: 'This is a long description for the test command.',
)]
final class CliCommandFixture implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void
    {
    }
}
