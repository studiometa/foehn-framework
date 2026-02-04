<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Studiometa\WPTempest\Attributes\AsPostType;
use Timber\Post;

#[AsPostType(name: 'project', singular: 'Project', plural: 'Projects', menuIcon: 'dashicons-portfolio')]
final class PostTypeFixture extends Post {}
