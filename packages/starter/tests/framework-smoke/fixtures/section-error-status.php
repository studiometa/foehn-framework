<?php

declare(strict_types=1);

/**
 * A mu-plugin that `SectionCacheTest` installs for one test: every page answers `400`,
 * as a template controller that refuses a search does with `status_header(400)`.
 */
add_action('template_redirect', static function (): void {
    status_header(400);
});
