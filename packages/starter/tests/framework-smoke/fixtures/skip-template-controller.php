<?php

declare(strict_types=1);

/**
 * A mu-plugin that `TemplateControllersTest` installs for one test: it skips the template
 * controllers on requests with `?smoke-skip-controllers`, so WordPress includes the
 * template file of the theme.
 */
add_filter(
    'foehn/template_controller/skip',
    static fn(bool $skip): bool => $skip || isset($_GET['smoke-skip-controllers']),
);
