<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

use WP_CLI;

/**
 * Helper class for WP-CLI output and interaction.
 *
 * Wraps WP_CLI static methods for easier testing and dependency injection.
 */
final class WpCli
{
    /**
     * Check if WP-CLI is available.
     */
    public static function isAvailable(): bool
    {
        return defined('WP_CLI') && \WP_CLI && class_exists(WP_CLI::class);
    }

    /**
     * Display an informational message.
     */
    public function log(string $message): void
    {
        WP_CLI::log($message);
    }

    /**
     * Display a success message.
     */
    public function success(string $message): void
    {
        WP_CLI::success($message);
    }

    /**
     * Display an error message and exit.
     */
    public function error(string $message, bool $exit = true): void
    {
        WP_CLI::error($message, $exit);
    }

    /**
     * End the process with an exit status of your own.
     *
     * `error()` always exits `1`, so a command that has to distinguish "the thing you
     * asked about is wrong" from "this check could not run at all" cannot express the
     * second one through it. Print the message with `error(exit: false)` first: this
     * only carries the status.
     */
    public function halt(int $status): void
    {
        WP_CLI::halt($status);
    }

    /**
     * Display a warning message.
     */
    public function warning(string $message): void
    {
        WP_CLI::warning($message);
    }

    /**
     * Display a line of text.
     */
    public function line(string $message = ''): void
    {
        WP_CLI::line($message);
    }

    /**
     * Colorize a string for output.
     */
    public function colorize(string $string): string
    {
        return WP_CLI::colorize($string);
    }

    /**
     * Ask for confirmation.
     *
     * @param string $question Question to ask
     * @param array<string, mixed> $assocArgs Arguments that may contain --yes flag
     */
    public function confirm(string $question, array $assocArgs = []): bool
    {
        if (($assocArgs['yes'] ?? null) !== null || ($assocArgs['y'] ?? null) !== null) {
            return true;
        }

        WP_CLI::confirm($question);

        return true;
    }

    /**
     * Prompt for user input.
     */
    public function prompt(string $question, string $default = ''): string
    {
        fwrite(STDOUT, $question . ($default !== '' ? " [{$default}]" : '') . ': ');
        $input = trim((string) fgets(STDIN));

        return $input !== '' ? $input : $default;
    }

    /**
     * Display a formatted table.
     *
     * @param array<int, array<string, mixed>> $items Items to display
     * @param array<int, string> $fields Fields to display
     */
    public function table(array $items, array $fields): void
    {
        WP_CLI\Utils\format_items('table', $items, $fields);
    }

    /**
     * Run a system command with a spinner.
     *
     * @param string $label Label to display
     * @param callable $callback Callback to execute
     */
    public function withSpinner(string $label, callable $callback): mixed
    {
        $this->log($label . '...');
        $result = $callback();
        $this->success('Done!');

        return $result;
    }

    /**
     * Get the path relative to the theme/plugin root.
     */
    public function getRelativePath(string $absolutePath): string
    {
        $root = defined('STYLESHEETPATH') ? \STYLESHEETPATH : getcwd();

        if (str_starts_with($absolutePath, (string) $root)) {
            return ltrim(substr($absolutePath, strlen((string) $root)), '/');
        }

        return $absolutePath;
    }

    /**
     * Show what a generated file would contain, without writing it.
     */
    public function previewGeneratedFile(GeneratedFile $file, int $maxLines = 50): void
    {
        $this->line('');
        $this->log($this->colorize('%YWould create:%n'));
        $this->log("  → {$this->getRelativePath($file->path)}");
        $this->line('');
        $this->log($this->colorize('%YWith content:%n'));
        $this->line('');

        $lines = explode("\n", $file->contents);

        foreach (array_slice($lines, 0, $maxLines) as $line) {
            $this->line('  ' . $line);
        }

        if (count($lines) > $maxLines) {
            $this->line('');
            $this->log($this->colorize('%C  ... (' . (count($lines) - $maxLines) . ' more lines)%n'));
        }
    }

    /**
     * Report that a generated file already exists and was left alone.
     */
    public function reportFileExists(GeneratedFile $file): void
    {
        $this->error(
            "File already exists: {$this->getRelativePath($file->path)}\n" . 'Use --force to overwrite.',
            exit: false,
        );
    }
}
