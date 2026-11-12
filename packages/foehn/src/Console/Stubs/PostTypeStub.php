<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;
use Tempest\Discovery\SkipDiscovery;

// The parent when the project declares no post type: the Føhn post model rather
// than Timber's, so the new type gets the query methods (`Event::query()`,
// `Event::find()`) the guides use. Not a docblock, which would be copied.
#[SkipDiscovery]
#[AsPostType(
    name: 'dummy-post-type',
    singular: 'Dummy Singular',
    plural: 'Dummy Plural',
    public: true,
    hasArchive: true,
    showInRest: true,
    menuIcon: 'dashicons-admin-post',
    supports: ['title', 'editor', 'thumbnail', 'excerpt'],
)]
final class PostTypeStub extends Post {}
