<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Models;

use Studiometa\Foehn\Attributes\AsTimberModel;

/**
 * Base page model with fluent query support.
 *
 * Extends Post and inherits query methods via QueriesPostType trait.
 * Automatically registered as Timber's classmap for the 'page' type.
 *
 * @example
 * ```php
 * // Get all top-level pages
 * Page::query()->parent(0)->orderBy('menu_order', 'ASC')->get();
 *
 * // Find a page by ID
 * Page::find(42);
 * ```
 */
#[AsTimberModel('page')]
class Page extends Post {}
