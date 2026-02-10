<?php

declare(strict_types=1);

/**
 * Fallback template.
 *
 * This file is required by WordPress and serves as the ultimate fallback
 * when no template controller handles the request.
 */

get_header();
?>

<main>
    <div class="container">
        <h1><?php esc_html_e('Page not found', 'starter-theme'); ?></h1>
        <p><?php esc_html_e('No template controller matched this request.', 'starter-theme'); ?></p>
    </div>
</main>

<?php
get_footer();
