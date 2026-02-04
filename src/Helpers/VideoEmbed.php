<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Helpers;

/**
 * Helper class to transform video URLs to privacy-friendly embed URLs.
 *
 * Supports YouTube and Vimeo video URLs in various formats.
 */
final class VideoEmbed
{
    private const YOUTUBE_PATTERNS = [
        // youtube.com/watch?v=VIDEO_ID
        '#(?:https?://)?(?:www\.)?youtube\.com/watch\?.*v=([a-zA-Z0-9_-]{11})#',
        // youtu.be/VIDEO_ID
        '#(?:https?://)?youtu\.be/([a-zA-Z0-9_-]{11})#',
        // youtube.com/embed/VIDEO_ID
        '#(?:https?://)?(?:www\.)?youtube\.com/embed/([a-zA-Z0-9_-]{11})#',
        // youtube.com/v/VIDEO_ID
        '#(?:https?://)?(?:www\.)?youtube\.com/v/([a-zA-Z0-9_-]{11})#',
        // youtube-nocookie.com/embed/VIDEO_ID
        '#(?:https?://)?(?:www\.)?youtube-nocookie\.com/embed/([a-zA-Z0-9_-]{11})#',
    ];

    private const VIMEO_PATTERNS = [
        // vimeo.com/VIDEO_ID
        '#(?:https?://)?(?:www\.)?vimeo\.com/(\d+)#',
        // vimeo.com/channels/CHANNEL/VIDEO_ID
        '#(?:https?://)?(?:www\.)?vimeo\.com/channels/[^/]+/(\d+)#',
        // player.vimeo.com/video/VIDEO_ID
        '#(?:https?://)?player\.vimeo\.com/video/(\d+)#',
        // vimeo.com/groups/GROUP/videos/VIDEO_ID
        '#(?:https?://)?(?:www\.)?vimeo\.com/groups/[^/]+/videos/(\d+)#',
    ];

    /**
     * Transform a video URL to a privacy-friendly embed URL.
     *
     * @param string $url The video URL
     * @param array{
     *     autoplay?: bool,
     *     loop?: bool,
     *     muted?: bool,
     *     nocookie?: bool
     * } $options Embed options
     * @return string|null The embed URL or null if unsupported
     */
    public static function embedUrl(string $url, array $options = []): ?string
    {
        $platform = self::detectPlatform($url);
        $id = self::extractId($url);

        if ($platform === null || $id === null) {
            return null;
        }

        $autoplay = $options['autoplay'] ?? false;
        $loop = $options['loop'] ?? false;
        $muted = $options['muted'] ?? $autoplay; // Muted follows autoplay by default
        $nocookie = $options['nocookie'] ?? true;

        if ($platform === 'youtube') {
            return self::buildYoutubeEmbedUrl($url, $id, $autoplay, $loop, $muted, $nocookie);
        }

        return self::buildVimeoEmbedUrl($id, $autoplay, $loop, $muted);
    }

    /**
     * Extract the video ID from a URL.
     *
     * @param string $url The video URL
     * @return string|null The video ID or null if not found
     */
    public static function extractId(string $url): ?string
    {
        // Try YouTube patterns
        foreach (self::YOUTUBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches) !== 1) {
                continue;
            }

            return $matches[1];
        }

        // Try Vimeo patterns
        foreach (self::VIMEO_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches) !== 1) {
                continue;
            }

            return $matches[1];
        }

        return null;
    }

    /**
     * Detect the video platform from a URL.
     *
     * @param string $url The video URL
     * @return string|null 'youtube', 'vimeo', or null if unsupported
     */
    public static function detectPlatform(string $url): ?string
    {
        foreach (self::YOUTUBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url) !== 1) {
                continue;
            }

            return 'youtube';
        }

        foreach (self::VIMEO_PATTERNS as $pattern) {
            if (preg_match($pattern, $url) !== 1) {
                continue;
            }

            return 'vimeo';
        }

        return null;
    }

    /**
     * Check if a URL is a supported video URL.
     *
     * @param string $url The video URL
     * @return bool True if supported
     */
    public static function isSupported(string $url): bool
    {
        return self::detectPlatform($url) !== null;
    }

    /**
     * Build a YouTube embed URL.
     */
    private static function buildYoutubeEmbedUrl(
        string $originalUrl,
        string $id,
        bool $autoplay,
        bool $loop,
        bool $muted,
        bool $nocookie,
    ): string {
        $domain = $nocookie ? 'www.youtube-nocookie.com' : 'www.youtube.com';
        $baseUrl = "https://{$domain}/embed/{$id}";

        $params = [];

        if ($autoplay) {
            $params['autoplay'] = '1';
        }

        if ($loop) {
            $params['loop'] = '1';
            // YouTube requires playlist parameter for loop to work
            $params['playlist'] = $id;
        }

        if ($muted) {
            $params['mute'] = '1';
        }

        // Extract timestamp from original URL
        $timestamp = self::extractYoutubeTimestamp($originalUrl);
        if ($timestamp !== null) {
            $params['start'] = (string) $timestamp;
        }

        if ($params === []) {
            return $baseUrl;
        }

        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Build a Vimeo embed URL.
     */
    private static function buildVimeoEmbedUrl(string $id, bool $autoplay, bool $loop, bool $muted): string
    {
        $baseUrl = "https://player.vimeo.com/video/{$id}";

        $params = [];

        if ($autoplay) {
            $params['autoplay'] = '1';
        }

        if ($loop) {
            $params['loop'] = '1';
        }

        if ($muted) {
            $params['muted'] = '1';
        }

        // Vimeo's dnt parameter for privacy
        $params['dnt'] = '1';

        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * Extract timestamp from a YouTube URL.
     *
     * Handles formats like ?t=120, ?t=2m30s, &t=90
     *
     * @param string $url The YouTube URL
     * @return int|null Timestamp in seconds or null
     */
    private static function extractYoutubeTimestamp(string $url): ?int
    {
        // Parse the URL to get query parameters
        $parsed = parse_url($url);
        $query = $parsed['query'] ?? '';

        parse_str($query, $params);

        if (!isset($params['t'])) {
            return null;
        }

        $time = $params['t'];

        // If it's already a number, return it
        if (is_numeric($time)) {
            return (int) $time;
        }

        // Parse formats like 2m30s, 1h2m30s
        $seconds = 0;

        if (preg_match('/(\d+)h/', $time, $matches) === 1) {
            $seconds += (int) $matches[1] * 3600;
        }

        if (preg_match('/(\d+)m/', $time, $matches) === 1) {
            $seconds += (int) $matches[1] * 60;
        }

        if (preg_match('/(\d+)s/', $time, $matches) === 1) {
            $seconds += (int) $matches[1];
        }

        return $seconds > 0 ? $seconds : null;
    }
}
