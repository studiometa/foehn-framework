<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsPostMeta;

/**
 * Meta declared on a class that is no model: one key for every post type, and one
 * for a post type that a plugin registers and nothing else in the project names.
 */
#[AsPostMeta(key: 'featured', type: 'boolean')]
#[AsPostMeta(key: 'gtin', objectSubtype: 'product_variation')]
final class SharedMeta {}
