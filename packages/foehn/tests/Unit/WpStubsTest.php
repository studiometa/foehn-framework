<?php

declare(strict_types=1);

/**
 * `tests/wp-stubs.php` is loaded by project test suites, after their own stubs. A
 * WordPress function it defines without a `function_exists()` guard would stop such a
 * suite with "Cannot redeclare", and the documented way to override a stub would not
 * work any more.
 */
describe('wp-stubs.php', function () {
    it('defines each WordPress function only when it does not exist yet', function () {
        $lines = explode("\n", (string) file_get_contents(dirname(__DIR__) . '/wp-stubs.php'));
        $unguarded = [];

        foreach ($lines as $index => $line) {
            // A top-level or guarded function; a method has a visibility keyword first.
            if (preg_match('/^\s*function\s+(\w+)\s*\(/', $line, $match) !== 1) {
                continue;
            }

            $name = $match[1];

            // The helpers of the stubs themselves: no project defines them.
            if (str_starts_with($name, 'wp_stub_')) {
                continue;
            }

            $previous = $index - 1;

            while ($previous >= 0 && preg_match('#^\s*(//.*|/?\*.*)?$#', $lines[$previous]) === 1) {
                $previous--;
            }

            if ($previous >= 0 && str_contains($lines[$previous], "function_exists('{$name}')")) {
                continue;
            }

            $unguarded[] = $name;
        }

        expect($unguarded)->toBe([]);
    });
});
