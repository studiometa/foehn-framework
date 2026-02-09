<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * Configuration for Timber.
 *
 * Create a config file in your app directory:
 *
 * ```php
 * // app/timber.config.php
 * use Studiometa\Foehn\Config\TimberConfig;
 *
 * return new TimberConfig(
 *     templatesDir: ['views', 'templates'],
 * );
 * ```
 */
final readonly class TimberConfig
{
    /**
     * @param string[] $templatesDir Timber templates directory names
     */
    public function __construct(
        public array $templatesDir = ['templates'],
    ) {}
}
