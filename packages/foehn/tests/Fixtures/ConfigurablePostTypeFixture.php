<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Timber\Post;

/**
 * A post type that customises its own registration.
 *
 * Extends Timber\Post, whose constructor is protected — which is the whole point of
 * the fixture: a class in that shape is not instantiable, and the discovery must
 * still see the interface it implements.
 */
#[AsPostType(name: 'configurable', singular: 'Configurable', plural: 'Configurables')]
final class ConfigurablePostTypeFixture extends Post implements ConfiguresPostType
{
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        return $builder->setRewrite(['slug' => 'configuré', 'with_front' => false]);
    }
}
