<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;

/**
 * Føhn in the `local` environment: ddev and CI.
 *
 * A file for one environment replaces foehn.config.php, it does not merge with it. So
 * this one starts from the shared file and changes only what a developer needs.
 *
 * `recordErrors` and `traceRequests` are on in `local` by default. They are written out
 * here to show where to turn them on elsewhere, for example for a day in a
 * `foehn.staging.config.php`. Read them with `wp foehn errors` and `wp foehn trace`.
 * `debug` raises a PHP warning for each class that discovery skips.
 */

/** @var FoehnConfig $shared */
$shared = require __DIR__ . '/foehn.config.php';

return new FoehnConfig(
    discoveryCacheStrategy: $shared->discoveryCacheStrategy,
    discoveryCachePath: $shared->discoveryCachePath,
    hooks: $shared->hooks,
    imageTransformer: $shared->imageTransformer,
    debug: true,
    recordErrors: true,
    traceRequests: true,
    cliNamespace: $shared->cliNamespace,
);
