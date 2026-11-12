<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

use Aws\S3\S3Client;

/**
 * The bucket `humanmade/s3-uploads` writes to, and a client for it.
 *
 * Shared by the features that store their own files beside the uploads: Glide's
 * transforms under `cache/glide/` and the page cache under `cache/pages/`. One place
 * reads the bucket configuration, so the two cannot reach different buckets.
 */
final class UploadsBucket
{
    /**
     * The bucket name, or null when the uploads stay on local disk.
     */
    public static function name(): ?string
    {
        if (!defined('S3_UPLOADS_BUCKET')) {
            return null;
        }

        $bucket = (string) constant('S3_UPLOADS_BUCKET');

        return $bucket === '' ? null : $bucket;
    }

    /**
     * A client for the same bucket `s3-uploads` writes to.
     *
     * Its own, rather than a second one built from constants. The bucket and the
     * region are constants, but everything that makes a non-AWS bucket reachable
     * is not: the plugin takes its endpoint, its path-style addressing and its
     * checksum settings from the `s3_uploads_s3_client_params` filter, which is
     * where Føhn's own `S3UploadsEndpoint` supplies them, and where a site adds
     * whatever R2 or Scaleway needs.
     *
     * Reading only the constants produced a client pointed at AWS while the
     * uploads went to MinIO — and the symptom was every transform 404ing with the
     * originals plainly present in the media library.
     */
    public static function client(): S3Client
    {
        // `class_exists` and not a `use`: the plugin is a dependency of the site,
        // never of the framework.
        if (class_exists('S3_Uploads\Plugin')) {
            /** @var S3Client */
            return call_user_func(['S3_Uploads\Plugin', 'get_instance'])->s3();
        }

        $config = [
            'version' => 'latest',
            'region' => defined('S3_UPLOADS_REGION') ? (string) constant('S3_UPLOADS_REGION') : 'us-east-1',
        ];

        if (defined('S3_UPLOADS_KEY') && defined('S3_UPLOADS_SECRET')) {
            $config['credentials'] = [
                'key' => (string) constant('S3_UPLOADS_KEY'),
                'secret' => (string) constant('S3_UPLOADS_SECRET'),
            ];
        }

        // Anything that is not AWS — MinIO, R2, Scaleway — needs its endpoint, and
        // path-style addressing because a bucket is not a subdomain there.
        if (defined('S3_UPLOADS_ENDPOINT') && (string) constant('S3_UPLOADS_ENDPOINT') !== '') {
            $config['endpoint'] = (string) constant('S3_UPLOADS_ENDPOINT');
            $config['use_path_style_endpoint'] = true;
        }

        // The plugin's own extension point, applied to this client too, so one
        // filter configures every client.
        if (function_exists('apply_filters')) {
            /** @var array<string, mixed> $config */
            $config = apply_filters('s3_uploads_s3_client_params', $config);
        }

        return new S3Client($config);
    }
}
