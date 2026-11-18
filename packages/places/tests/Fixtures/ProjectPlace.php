<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Places\Models\Place;

/**
 * A project subclass with a project field, as the spec shows it.
 */
#[AsTimberModel('place')]
#[AsPostMeta(key: 'partner_since', type: 'integer', control: 'number', label: 'Partner since')]
final class ProjectPlace extends Place
{
    public function partnerSince(): ?int
    {
        $value = $this->meta('partner_since');

        return is_numeric($value) ? (int) $value : null;
    }
}
