<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * Interface for ACF Options Pages that define fields.
 *
 * Implement this interface to define ACF fields for your options page.
 * This is optional - options pages can also use field groups defined elsewhere.
 */
interface AcfOptionsPageInterface
{
    /**
     * Define ACF fields for this options page.
     *
     * @return FieldsBuilder The configured fields builder
     */
    public static function fields(): FieldsBuilder;
}
