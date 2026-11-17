<?php

declare(strict_types=1);

/**
 * The starter's `arrow-right` icon, printed by `icon()` from a Twig string.
 *
 * Run by `IconsTest` through `wp eval-file`. One line of output, because
 * `Site::wp()` hands back the last line of a command.
 */

$html = (string) \Timber\Timber::compile_string("{{ icon('arrow-right', { size: 16 }) }}");

printf(
    'icon=%d',
    (int) (str_starts_with($html, '<svg') && str_contains($html, 'width="16"') && str_contains($html, '<path')),
);
