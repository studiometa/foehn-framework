<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

/**
 * A file the generator produced but has not written.
 *
 * Generating and writing are separate steps, so `--dry-run` is simply a command that
 * never calls write() rather than a flag threaded through the generator.
 */
final readonly class GeneratedFile
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {}
}
