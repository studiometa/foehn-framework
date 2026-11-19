<?php

declare(strict_types=1);

use Studiometa\Foehn\Places\Config\PlacesConfig;

/**
 * The default PlacesConfig, shipped by the package that owns it.
 *
 * ConfigLoader reads vendor locations before app ones, so a project's own
 * `app/places.config.php` overrides this.
 */
return new PlacesConfig();
