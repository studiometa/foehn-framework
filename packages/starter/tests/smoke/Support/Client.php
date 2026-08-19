<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Smoke\Support;

use CurlHandle;
use RuntimeException;

/**
 * The smallest HTTP client that can test a page cache.
 *
 * Pest ships no HTTP client, and the one plugin that would provide one drives a real
 * browser through Playwright — the wrong tool for reading a response header and looking
 * at a file on disk. `curl` is already in the PHP build, so this adds no dependency.
 *
 * Two things it deliberately does not do: follow redirects, because a redirect would hide
 * which URL was actually keyed; and verify the certificate, because ddev signs its own.
 */
final readonly class Client
{
    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $headers
     */
    public static function get(string $url, array $cookies = [], array $headers = []): Response
    {
        $handle = curl_init($url);

        if (!$handle instanceof CurlHandle) {
            throw new RuntimeException('Could not initialise curl for ' . $url);
        }

        $collected = [];

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_map(
                static fn(string $name, string $value): string => $name . ': ' . $value,
                array_keys($headers),
                array_values($headers),
            ),
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $_handle, string $line) use (&$collected): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $collected[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if ($cookies !== []) {
            $pairs = [];

            foreach ($cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }

            curl_setopt($handle, CURLOPT_COOKIE, implode('; ', $pairs));
        }

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);

        // No curl_close(): deprecated since PHP 8.5, and the handle is freed with the
        // CurlHandle object anyway.

        if (!is_string($body)) {
            throw new RuntimeException(sprintf('Request to %s failed: %s', $url, $error));
        }

        /** @var array<string, string> $collected */
        return new Response($status, $collected, $body);
    }

    /**
     * Whether a URL answers at all, for deciding to skip rather than fail.
     */
    public static function reachable(string $url): bool
    {
        try {
            return self::get($url)->status > 0;
        } catch (RuntimeException) {
            return false;
        }
    }
}
