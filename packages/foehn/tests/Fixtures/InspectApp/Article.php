<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsTimberModel;
use Timber\Post;

/**
 * A model for core `post`, which WordPress registers and Føhn does not.
 */
#[AsTimberModel('post')]
#[AsPostMeta(key: 'subtitle')]
final class Article extends Post {}
