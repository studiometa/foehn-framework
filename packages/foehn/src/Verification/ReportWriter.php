<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification;

/**
 * Writes the JSON report where CI will pick it up, in one step or not at all.
 *
 * The write goes to a sibling temporary file and is then `rename()`d over the
 * target, which on the same filesystem is atomic: a reader either sees the
 * previous report or the new one, never a half-written one. That matters because
 * the consumer is usually another process — a CI step that uploads the artifact,
 * or a job that parses it while the command is still running.
 *
 * A failure to write is a verification infrastructure failure, not a check
 * failure: the run inspected the site and then lost the answer.
 */
final readonly class ReportWriter
{
    /**
     * Write the report, and hand back the absolute path it landed at.
     *
     * @throws VerificationFailure When the report could not be written where it was asked for
     */
    public function write(string $path, VerificationReport $report): string
    {
        $target = $this->resolve($path);
        $directory = dirname($target);

        if (!is_dir($directory)) {
            throw new VerificationFailure("Cannot write the report: {$directory} is not a directory.");
        }

        if (!is_writable($directory)) {
            throw new VerificationFailure("Cannot write the report: {$directory} is not writable.");
        }

        // uniqid() rather than tempnam(): the temporary file has to be a sibling of the
        // target for rename() to stay atomic, and tempnam() would put it in the system
        // temporary directory — where a rename across filesystems is a copy, and a copy
        // is exactly the partial file this method exists to prevent.
        $temporary = $target . '.' . uniqid('', true) . '.tmp';
        $contents = $report->toJson();

        if (!$this->quietly(static fn(): bool => file_put_contents($temporary, $contents) === strlen($contents))) {
            $this->discard($temporary);

            throw new VerificationFailure("Cannot write the report: {$temporary} could not be written.");
        }

        if (!$this->quietly(static fn(): bool => rename($temporary, $target))) {
            $this->discard($temporary);

            throw new VerificationFailure("Cannot write the report: {$target} could not be replaced.");
        }

        return $target;
    }

    /**
     * Where a `--output` value points.
     *
     * Relative paths resolve from `ABSPATH` rather than from the working directory: a
     * deployment script and a CI job run the command from wherever they happen to be,
     * and a report that lands in a different place each time is not an artifact.
     */
    public function resolve(string $path): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        return $this->base() . ltrim($path, '/');
    }

    /**
     * Remove the half-written sibling, so a failed write leaves the directory as it was.
     */
    private function discard(string $temporary): void
    {
        $this->quietly(static fn(): bool => !is_file($temporary) || unlink($temporary));
    }

    /**
     * Run a filesystem call without letting its warning escape.
     *
     * A report that could not be written is reported as a {@see VerificationFailure}
     * with a message naming the path — which is more use than a PHP warning, and is the
     * only one of the two the exit status is derived from. The `try`/`finally` is what
     * keeps the handler stack balanced whatever the call does.
     *
     * @param callable(): bool $operation
     */
    private function quietly(callable $operation): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * `ABSPATH` with its trailing slash, or the working directory outside WordPress.
     */
    private function base(): string
    {
        $base = defined('ABSPATH') ? (string) constant('ABSPATH') : (string) getcwd();

        return rtrim($base, '/') . '/';
    }
}
