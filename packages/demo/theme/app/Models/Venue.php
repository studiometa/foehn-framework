<?php

declare(strict_types=1);

namespace Demo\Models;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Places\Models\Place;

/**
 * A venue that shows the work: the package `Place` with one project field.
 *
 * `#[AsTimberModel('place')]` maps the subclass to the package post type, so
 * `PlaceSearch::current()`, `Place::query()` and Timber all give a `Venue`. The
 * field gets a native input in the "Fields" meta box of the place editor.
 */
#[AsTimberModel('place')]
#[AsPostMeta(key: 'opening_hours', type: 'string', control: 'text', label: 'Opening hours')]
final class Venue extends Place
{
    public function openingHours(): ?string
    {
        $value = $this->meta('opening_hours');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
