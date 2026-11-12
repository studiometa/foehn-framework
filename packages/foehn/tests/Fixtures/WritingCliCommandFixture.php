<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;

#[AsCliCommand(
    name: 'test:write',
    description: 'Write something',
    longDescription: "## OPTIONS\n\n[--force]\n: Run in production\n\n[--dry-run]\n: Write nothing",
    writes: true,
)]
final class WritingCliCommandFixture implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void
    {
        wp_stub_record('fixture_command_ran', $assocArgs);
    }
}
