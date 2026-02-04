<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\Foehn\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(name: 'project', singular: 'Project', plural: 'Projects', menuIcon: 'dashicons-portfolio')]
final class PostTypeFixture extends Post {}
