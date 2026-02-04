<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsCliCommand;
use Studiometa\WPTempest\Console\CliCommandInterface;

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
