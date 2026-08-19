<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Interface for ACF field groups.
 *
 * Implement this interface to create ACF field groups with the #[AsAcfFieldGroup] attribute.
 */
interface AcfFieldGroupInterface
{
    /**
     * Define ACF fields for this group.
     *
     * @return FieldsBuilder The configured fields builder
     */
    public static function fields(): FieldsBuilder;
}
