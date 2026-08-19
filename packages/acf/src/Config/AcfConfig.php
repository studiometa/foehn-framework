<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * Configuration for ACF integration.
 *
 * Create a config file in your app directory:
 *
 * ```php
 * // app/acf.config.php
 * use Studiometa\Foehn\Config\AcfConfig;
 *
 * return new AcfConfig(
 *     transformFields: true,
 * );
 * ```
 */
final readonly class AcfConfig
{
    /**
     * @param bool $transformFields Transform ACF block fields via Timber's ACF integration.
     *                              When enabled, raw ACF values (image IDs, post IDs, etc.)
     *                              are automatically converted to Timber objects.
     */
    public function __construct(
        public bool $transformFields = true,
    ) {}
}
