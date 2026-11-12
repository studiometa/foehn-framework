<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * A class that writes sample content, run by `wp foehn seed`.
 *
 * `run()` must be idempotent: find a post by slug, an attachment by meta or a
 * menu by name before creating it. Throw an exception to stop the seed with an
 * error.
 */
interface SeederInterface
{
    /**
     * Write the content, or update the content that a previous run wrote.
     */
    public function run(): void;
}
