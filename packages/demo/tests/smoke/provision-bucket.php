<?php

declare(strict_types=1);

/**
 * Create the demo's bucket and let the world read it, through the plugin's own client.
 *
 * Run by the ddev post-start hook with `wp eval-file`, and safe to run again.
 *
 * Doing this through `S3_Uploads\Plugin::get_instance()->s3()` rather than through a
 * separate client is deliberate: it is the cheapest proof that the endpoint, the
 * credentials and the path-style addressing the framework supplies are all right. If
 * this fails, nothing else about uploads is worth debugging yet.
 */

if (!defined('S3_UPLOADS_BUCKET')) {
    echo "S3_UPLOADS_BUCKET is not defined — uploads stay on local disk\n";

    return;
}

$bucket = S3_UPLOADS_BUCKET;
$s3 = S3_Uploads\Plugin::get_instance()->s3();

if (!$s3->doesBucketExist($bucket)) {
    $s3->createBucket(['Bucket' => $bucket]);
    echo "created bucket {$bucket}\n";
}

// The plugin uploads objects with a public-read ACL, which is how AWS serves them and
// is not how MinIO does: it ignores per-object ACLs unless the bucket policy allows
// anonymous reads. Without this every upload succeeds and every image 404s — the
// failure that looks like a broken theme and is a storage configuration.
$s3->putBucketPolicy([
    'Bucket' => $bucket,
    'Policy' => json_encode([
        'Version' => '2012-10-17',
        'Statement' => [
            [
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => ["arn:aws:s3:::{$bucket}/*"],
            ],
        ],
    ], JSON_THROW_ON_ERROR),
]);

echo "bucket {$bucket} is readable\n";
