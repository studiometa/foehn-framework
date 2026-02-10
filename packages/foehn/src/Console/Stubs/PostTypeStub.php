<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsPostType;
use Tempest\Discovery\SkipDiscovery;
use Timber\Post;

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
final class PostTypeStub extends Post
{
    // Add custom methods for your post type here
    //
    // Example:
    // public function formattedDate(): string
    // {
    //     return $this->date('F j, Y');
    // }
}
