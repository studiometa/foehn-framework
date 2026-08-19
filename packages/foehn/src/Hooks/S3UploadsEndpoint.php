<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks;

use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Point humanmade/s3-uploads at an S3-compatible endpoint that is not AWS.
 *
 * The plugin is configured through constants, which cover AWS and nothing else:
 * Cloudflare R2, Scaleway, DigitalOcean Spaces, Ceph and MinIO all need an
 * explicit endpoint, and its documentation tells you to write the filter that
 * supplies one into an mu-plugin of your own. This is that filter, read from the
 * environment like everything else about a deploy, and opted into the way every
 * other hook class is:
 *
 * ```php
 * // app/foehn.config.php
 * return new FoehnConfig(hooks: [S3UploadsEndpoint::class]);
 * ```
 *
 * Reads:
 * - `S3_UPLOADS_ENDPOINT` — the API endpoint. Nothing happens without it.
 * - `S3_UPLOADS_PATH_STYLE` — bucket in the path rather than the hostname. MinIO
 *   and Ceph need it; R2 and Scaleway do not.
 * - `S3_UPLOADS_CHECKSUMS` — set false against a provider that rejects the
 *   integrity headers AWS SDK 3.337 made the default.
 *
 * The filter is harmless when the plugin is absent: nothing applies it, so the
 * method never runs.
 */
final class S3UploadsEndpoint
{
    /**
     * @param array<string, mixed> $params Client parameters, as the AWS SDK takes them
     * @return array<string, mixed>
     */
    #[AsFilter('s3_uploads_s3_client_params')]
    public function endpoint(array $params): array
    {
        $endpoint = self::env('S3_UPLOADS_ENDPOINT');

        // An endpoint is what this class exists to supply. Without one the site is
        // talking to AWS, where every default is already right.
        if (!is_string($endpoint) || $endpoint === '') {
            return $params;
        }

        $params['endpoint'] = $endpoint;
        $params['use_path_style_endpoint'] = self::flag('S3_UPLOADS_PATH_STYLE', false);

        // AWS SDK 3.337 began sending integrity headers that several S3-compatible
        // APIs reject outright, which surfaces as uploads failing rather than as
        // anything mentioning checksums.
        if (!self::flag('S3_UPLOADS_CHECKSUMS', true)) {
            $params['request_checksum_calculation'] = 'when_required';
            $params['response_checksum_validation'] = 'when_required';
        }

        return $params;
    }

    /**
     * Read a variable the way the generated wp-config.php does.
     */
    private static function env(string $key): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        // getenv() reports an unset variable as false, which is not a value any of
        // these hold.
        if ($value === false) {
            return null;
        }

        return $value;
    }

    /**
     * Read a boolean, treating an unset variable as the default.
     *
     * `filter_var` is what turns the string "false" — which is all an .env file can
     * hold — into false rather than into a truthy non-empty string.
     */
    private static function flag(string $key, bool $default): bool
    {
        $value = self::env($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
