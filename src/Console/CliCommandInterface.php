<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Console;

/**
 * Interface for WP-CLI commands.
 */
interface CliCommandInterface
{
    /**
     * Execute the command.
     *
     * @param array<int, string> $args Positional arguments
     * @param array<string, string> $assocArgs Named arguments (--key=value)
     */
    public function __invoke(array $args, array $assocArgs): void;
}
