<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\AcfConfig;

/**
 * The default AcfConfig, shipped by the package that owns it.
 *
 * ConfigLoader reads vendor locations before app ones, so a project's own
 * `app/acf.config.php` still overrides this. That is the whole mechanism: a
 * package supplies its defaults through a config file, and needs no
 * service-provider concept to do it.
 */
return new AcfConfig();
