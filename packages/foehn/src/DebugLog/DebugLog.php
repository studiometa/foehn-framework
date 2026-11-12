<?php

declare(strict_types=1);

namespace Studiometa\Foehn\DebugLog;

/**
 * The debug log WordPress writes when `WP_DEBUG` and `WP_DEBUG_LOG` are on.
 *
 * WordPress points PHP's `error_log` setting at this file (`wp_debug_mode()`), so it
 * holds the PHP warnings, notices and fatal errors, and every `error_log()` call of
 * WordPress, plugins and the theme.
 */
final readonly class DebugLog
{
    /**
     * The end of the file that is read. A debug log can grow to gigabytes: its last
     * megabytes hold the entries an agent asks about.
     */
    public const int MAX_BYTES = 8 * 1024 * 1024;

    /**
     * @param string|null $path The file, or null when `WP_DEBUG_LOG` is off
     * @param bool $writing Whether WordPress writes to it: `WP_DEBUG` is on too
     */
    public function __construct(
        public ?string $path,
        public bool $writing,
    ) {}

    /**
     * The file `wp_debug_mode()` gives to PHP: `wp-content/debug.log` when
     * `WP_DEBUG_LOG` is true, or the path `WP_DEBUG_LOG` holds.
     */
    public static function fromWordPress(): self
    {
        return self::fromSettings(
            defined('WP_DEBUG_LOG') ? constant('WP_DEBUG_LOG') : false,
            defined('WP_DEBUG') ? constant('WP_DEBUG') : false,
            defined('WP_CONTENT_DIR') ? (string) constant('WP_CONTENT_DIR') : '',
        );
    }

    /**
     * The same, from the values of the constants: the tests cannot define them twice.
     */
    public static function fromSettings(mixed $debugLog, mixed $debug, string $contentDirectory): self
    {
        $path = is_string($debugLog) && $debugLog !== '' ? $debugLog : null;

        if (is_scalar($debugLog) && in_array(strtolower((string) $debugLog), ['true', '1'], true)) {
            $path = $contentDirectory . '/debug.log';
        }

        return new self($path, $path !== null && (bool) $debug);
    }

    /**
     * The end of the file, from the first whole line, and the size of the file.
     *
     * @return array{text: string, size: int}|null Null when the file does not exist or cannot be read
     */
    public function read(): ?array
    {
        if ($this->path === null || !is_file($this->path) || !is_readable($this->path)) {
            return null;
        }

        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            return null;
        }

        $size = (int) filesize($this->path);

        if ($size > self::MAX_BYTES) {
            fseek($handle, $size - self::MAX_BYTES);
        }

        $text = stream_get_contents($handle);
        fclose($handle);

        if ($text === false) {
            return null;
        }

        if ($size > self::MAX_BYTES) {
            $newline = strpos($text, "\n");
            $text = $newline === false ? '' : substr($text, $newline + 1);
        }

        return ['text' => $text, 'size' => $size];
    }
}
