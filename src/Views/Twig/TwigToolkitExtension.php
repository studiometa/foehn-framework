<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\TwigToolkit\Extension;

/**
 * Registers studiometa/twig-toolkit extension for Timber/Twig.
 *
 * Provides useful Twig helpers:
 * - `html_classes()` - Conditional CSS classes
 * - `html_styles()` - Conditional inline styles
 * - `html_attributes()` - Render HTML attributes
 * - `{% element %}` tag - Dynamic element rendering
 *
 * @see https://github.com/studiometa/twig-toolkit
 */
#[AsTwigExtension]
final class TwigToolkitExtension extends Extension {}
