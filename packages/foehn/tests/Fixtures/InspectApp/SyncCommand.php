<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;

#[AsCliCommand(name: 'shop:sync', description: 'Sync the catalogue')]
final class SyncCommand implements CliCommandInterface
{
    public function __invoke(array $args, array $assocArgs): void {}
}
