<?php

declare(strict_types=1);

/**
 * The starter footer, rendered the way a plugin template does: with `Timber::compile()`
 * and `Timber::context()`, not through Føhn's view engine.
 *
 * Run by `ContextProvidersTest` through `wp eval-file`. One line of output, because
 * `Site::wp()` hands back the last line of a command.
 */

$html = (string) \Timber\Timber::compile('components/footer.twig', \Timber\Timber::context());

printf('current_year=%d', (int) str_contains($html, date('Y')));
