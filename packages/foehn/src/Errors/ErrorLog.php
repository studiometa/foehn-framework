<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Errors;

/**
 * The file the error recorder writes to and `wp foehn errors` reads: one JSON object
 * per line.
 *
 * The file has a cap. When the next entry would make it larger than `MAX_BYTES`, it
 * becomes `errors.1.jsonl`, the previous `errors.1.jsonl` becomes `errors.2.jsonl`,
 * and the oldest file is deleted. So the log never holds more than `MAX_FILES`
 * files of `MAX_BYTES`.
 *
 * The browser log has the same format and the same cap. `@studiometa/foehn-vite-plugin`
 * writes it, and `wp foehn errors --browser` reads it.
 */
final readonly class ErrorLog
{
    public const int MAX_BYTES = 256 * 1024;

    public const int MAX_FILES = 3;

    public const string BROWSER = 'browser-errors';

    public function __construct(
        private string $directory,
        private string $name = 'errors',
    ) {}

    /**
     * The log in `wp-content/cache/foehn/errors`, beside the discovery cache.
     */
    public static function inContentDirectory(): self
    {
        $base = defined('WP_CONTENT_DIR') ? constant('WP_CONTENT_DIR') . '/cache' : sys_get_temp_dir();

        return new self($base . '/foehn/errors');
    }

    /**
     * The browser log the Vite dev server writes beside its hot file, in the `dist`
     * directory of the theme, where `ViteManifest::fromTheme()` reads the hot file.
     */
    public static function browserInTheme(): self
    {
        return new self(rtrim(get_template_directory(), '/') . '/dist', self::BROWSER);
    }

    /**
     * The newest file of the log.
     */
    public function file(): string
    {
        return $this->path(0);
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Add an entry at the end of the log.
     *
     * @param array<string, mixed> $entry
     */
    public function append(array $entry): void
    {
        $line = json_encode(
            $entry,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        if ($line === false) {
            return;
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            return;
        }

        $file = $this->path(0);
        clearstatcache(true, $file);

        if (is_file($file) && ((int) filesize($file) + strlen($line) + 1) > self::MAX_BYTES) {
            $this->rotate();
        }

        file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Every entry, the newest first.
     *
     * A line that is not a JSON object, such as a line cut by a full disk, is skipped.
     *
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        $entries = [];

        for ($index = 0; $index < self::MAX_FILES; $index++) {
            $file = $this->path($index);

            if (!is_file($file)) {
                continue;
            }

            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach (array_reverse($lines === false ? [] : $lines) as $line) {
                $entry = json_decode($line, true);

                if (is_array($entry)) {
                    /** @var array<string, mixed> $entry */
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    /**
     * Delete every file of the log.
     *
     * @return int The number of files deleted
     */
    public function clear(): int
    {
        $deleted = 0;

        for ($index = 0; $index < self::MAX_FILES; $index++) {
            $file = $this->path($index);

            if (is_file($file) && unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Move each file one place down and delete the oldest.
     */
    private function rotate(): void
    {
        $oldest = $this->path(self::MAX_FILES - 1);

        if (is_file($oldest)) {
            unlink($oldest);
        }

        for ($index = self::MAX_FILES - 2; $index >= 0; $index--) {
            $file = $this->path($index);

            if (is_file($file)) {
                rename($file, $this->path($index + 1));
            }
        }
    }

    private function path(int $index): string
    {
        return sprintf('%s/%s%s.jsonl', rtrim($this->directory, '/'), $this->name, $index === 0 ? '' : '.' . $index);
    }
}
