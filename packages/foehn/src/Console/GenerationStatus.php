<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

/**
 * What happened to a generated file, or what would happen to it on `--dry-run`.
 *
 * The values are the `status` strings `make:` commands print with `--format=json`.
 */
enum GenerationStatus: string
{
    case Created = 'created';
    case Overwritten = 'overwritten';
    case WouldCreate = 'would-create';
    case WouldOverwrite = 'would-overwrite';

    /**
     * The file exists and `--force` was not given, so it was, or would be, left alone.
     */
    case Exists = 'exists';

    public function isDryRun(): bool
    {
        return $this === self::WouldCreate || $this === self::WouldOverwrite;
    }
}
