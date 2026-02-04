<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Contracts;

use Studiometa\WPTempest\PostTypes\PostTypeBuilder;

/**
 * Interface for post type models that need custom configuration.
 *
 * Implement this interface on your Timber\Post model class to customize
 * the post type registration beyond what the #[AsPostType] attribute provides.
 */
interface ConfiguresPostType
{
    /**
     * Configure the post type builder with custom settings.
     *
     * This method is called after the attribute settings are applied,
     * allowing you to override or extend the configuration.
     *
     * @param PostTypeBuilder $builder The builder pre-configured from the attribute
     * @return PostTypeBuilder The modified builder
     */
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder;
}
