<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

/**
 * Logging helper for WordPress debug.log.
 *
 * Provides a simple API for logging messages with levels and context.
 * Only writes to log when WP_DEBUG_LOG is enabled.
 *
 * Usage:
 * ```php
 * use Studiometa\Foehn\Helpers\Log;
 *
 * Log::info('User logged in', ['user_id' => 123]);
 * Log::error('Payment failed', ['order_id' => 456, 'error' => $e->getMessage()]);
 * Log::debug('Query executed', ['sql' => $query, 'time' => $duration]);
 * ```
 */
final class Log
{
    /**
     * Log an emergency message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::log('EMERGENCY', $message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function alert(string $message, array $context = []): void
    {
        self::log('ALERT', $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log('CRITICAL', $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function notice(string $message, array $context = []): void
    {
        self::log('NOTICE', $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Custom log handler for testing.
     *
     * @var callable|null
     */
    private static $handler = null;

    /**
     * Set a custom log handler (for testing).
     *
     * @param callable|null $handler Handler function receiving (string $message)
     */
    public static function setHandler(?callable $handler): void
    {
        self::$handler = $handler;
    }

    /**
     * Write a log entry.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] " . self::formatMessage($level, $message, $context);

        if (self::$handler !== null) {
            (self::$handler)($formattedMessage);

            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($formattedMessage);
    }

    /**
     * Format the log message with context.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return string Formatted message
     */
    private static function formatMessage(string $level, string $message, array $context): string
    {
        $formatted = "[FOEHN.{$level}] {$message}";

        if ($context !== []) {
            $formatted .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $formatted;
    }

    /**
     * Override enabled state for testing.
     */
    private static ?bool $enabled = null;

    /**
     * Set enabled state (for testing).
     *
     * @param bool|null $enabled Null to use WP_DEBUG_LOG constant
     */
    public static function setEnabled(?bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if logging is enabled.
     *
     * @return bool True if WP_DEBUG_LOG is enabled
     */
    private static function isEnabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        if (!defined('WP_DEBUG_LOG')) {
            return false;
        }

        return (bool) WP_DEBUG_LOG;
    }
}
