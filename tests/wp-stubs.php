<?php

declare(strict_types=1);

/**
 * WordPress function stubs for unit testing.
 *
 * These stubs allow testing apply() code paths without a real WordPress installation.
 * Functions record their calls in a global array for assertion.
 */

// Global call recorder
$GLOBALS['wp_stub_calls'] = [];

function wp_stub_reset(): void
{
    $GLOBALS['wp_stub_calls'] = [];
    $GLOBALS['wp_stub_logged_in'] = false;
    $GLOBALS['wp_stub_user_can'] = [];
    $GLOBALS['wp_stub_acf_fields'] = [];
    $GLOBALS['wp_stub_acf_field_objects'] = [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function wp_stub_get_calls(string $function): array
{
    return array_values(array_filter($GLOBALS['wp_stub_calls'], fn(array $call) => $call['function'] === $function));
}

function wp_stub_record(string $function, array $args): void
{
    $GLOBALS['wp_stub_calls'][] = ['function' => $function, 'args' => $args];
}

// ──────────────────────────────────────────────
// WordPress classes (minimal stubs for test runtime)
// ──────────────────────────────────────────────

if (!class_exists('WP_Post_Type')) {
    class WP_Post_Type
    {
        public string $name;

        public function __construct(string $name = '')
        {
            $this->name = $name;
        }
    }
}

if (!class_exists('WP_Taxonomy')) {
    class WP_Taxonomy
    {
        public string $name;

        public function __construct(string $name = '')
        {
            $this->name = $name;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            public string $code = '',
            public string $message = '',
        ) {}
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_name = '';
        public string $post_type = 'post';
    }
}

if (!class_exists('WP_Term')) {
    class WP_Term
    {
        public int $term_id = 0;
        public string $slug = '';
        public string $taxonomy = '';
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        public bool $is_main_query = true;
        public array $posts = [];
        public int $post_count = 0;
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID = 0;
        public string $user_login = '';
        public string $user_email = '';
        public string $display_name = '';
    }
}

if (!class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';
        public string $posts = 'wp_posts';
        public string $postmeta = 'wp_postmeta';
        public string $users = 'wp_users';
        public string $usermeta = 'wp_usermeta';
        public string $options = 'wp_options';

        public function get_results(string $query): array
        {
            return [];
        }

        public function prepare(string $query, mixed ...$args): string
        {
            return sprintf($query, ...$args);
        }
    }
}

// ──────────────────────────────────────────────
// Hooks
// ──────────────────────────────────────────────

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        wp_stub_record('add_action', compact('hook', 'callback', 'priority', 'acceptedArgs'));
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        wp_stub_record('add_filter', compact('hook', 'callback', 'priority', 'acceptedArgs'));
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $hook, callable|string $callback, int $priority = 10): bool
    {
        wp_stub_record('remove_action', compact('hook', 'callback', 'priority'));

        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, callable|string $callback, int $priority = 10): bool
    {
        wp_stub_record('remove_filter', compact('hook', 'callback', 'priority'));

        return true;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void
    {
        wp_stub_record('add_shortcode', compact('tag', 'callback'));
    }
}

// ──────────────────────────────────────────────
// Post types & Taxonomies
// ──────────────────────────────────────────────

if (!function_exists('register_post_type')) {
    function register_post_type(string $postType, array $args = []): WP_Post_Type|WP_Error
    {
        wp_stub_record('register_post_type', compact('postType', 'args'));

        return new WP_Post_Type($postType);
    }
}

if (!function_exists('register_taxonomy')) {
    function register_taxonomy(string $taxonomy, $objectType = null, array $args = []): WP_Taxonomy|WP_Error
    {
        wp_stub_record('register_taxonomy', compact('taxonomy', 'objectType', 'args'));

        return new WP_Taxonomy($taxonomy);
    }
}

// ──────────────────────────────────────────────
// Menus
// ──────────────────────────────────────────────

if (!function_exists('register_nav_menus')) {
    function register_nav_menus(array $locations): void
    {
        wp_stub_record('register_nav_menus', compact('locations'));
    }
}

if (!function_exists('has_nav_menu')) {
    function has_nav_menu(string $location): bool
    {
        wp_stub_record('has_nav_menu', compact('location'));

        return $GLOBALS['wp_stub_nav_menus'][$location] ?? false;
    }
}

// ──────────────────────────────────────────────
// Blocks
// ──────────────────────────────────────────────

if (!function_exists('register_block_type')) {
    function register_block_type(string $blockName, array $args = []): void
    {
        wp_stub_record('register_block_type', compact('blockName', 'args'));
    }
}

if (!function_exists('register_block_pattern')) {
    function register_block_pattern(string $name, array $config = []): void
    {
        wp_stub_record('register_block_pattern', compact('name', 'config'));
    }
}

if (!function_exists('acf_register_block_type')) {
    function acf_register_block_type(array $config): void
    {
        wp_stub_record('acf_register_block_type', compact('config'));
    }
}

if (!function_exists('acf_add_local_field_group')) {
    function acf_add_local_field_group(array $group): void
    {
        wp_stub_record('acf_add_local_field_group', compact('group'));
    }
}

if (!function_exists('acf_add_options_page')) {
    function acf_add_options_page(array $config): array
    {
        wp_stub_record('acf_add_options_page', compact('config'));

        return $config;
    }
}

if (!function_exists('acf_add_options_sub_page')) {
    function acf_add_options_sub_page(array $config): array
    {
        wp_stub_record('acf_add_options_sub_page', compact('config'));

        return $config;
    }
}

if (!function_exists('get_field')) {
    function get_field(string $selector, mixed $postId = false, bool $formatValue = true): mixed
    {
        wp_stub_record('get_field', compact('selector', 'postId', 'formatValue'));

        return $GLOBALS['wp_stub_acf_fields'][$postId][$selector] ?? null;
    }
}

if (!function_exists('get_fields')) {
    function get_fields(mixed $postId = false, bool $formatValue = true): array|false
    {
        wp_stub_record('get_fields', compact('postId', 'formatValue'));

        return $GLOBALS['wp_stub_acf_fields'][$postId] ?? false;
    }
}

if (!function_exists('get_field_object')) {
    function get_field_object(string $selector, mixed $postId = false, bool $formatValue = true): array|false
    {
        wp_stub_record('get_field_object', compact('selector', 'postId', 'formatValue'));

        return $GLOBALS['wp_stub_acf_field_objects'][$postId][$selector] ?? false;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title) ?? $title);
    }
}

// ──────────────────────────────────────────────
// REST API
// ──────────────────────────────────────────────

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): void
    {
        wp_stub_record('register_rest_route', compact('namespace', 'route', 'args'));
    }
}

// ──────────────────────────────────────────────
// Template conditionals
// ──────────────────────────────────────────────

if (!function_exists('is_404')) {
    function is_404(): bool
    {
        return $GLOBALS['wp_stub_template'] === '404';
    }
}

if (!function_exists('is_search')) {
    function is_search(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'search';
    }
}

if (!function_exists('is_front_page')) {
    function is_front_page(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'front-page';
    }
}

if (!function_exists('is_home')) {
    function is_home(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'home';
    }
}

if (!function_exists('is_singular')) {
    function is_singular(): bool
    {
        return in_array($GLOBALS['wp_stub_template'] ?? '', ['single', 'page', 'attachment', 'singular'], true);
    }
}

if (!function_exists('is_single')) {
    function is_single(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'single';
    }
}

if (!function_exists('is_page')) {
    function is_page(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'page';
    }
}

if (!function_exists('is_attachment')) {
    function is_attachment(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'attachment';
    }
}

if (!function_exists('is_archive')) {
    function is_archive(): bool
    {
        return in_array(
            $GLOBALS['wp_stub_template'] ?? '',
            ['archive', 'category', 'tag', 'taxonomy', 'author', 'date'],
            true,
        );
    }
}

if (!function_exists('is_post_type_archive')) {
    function is_post_type_archive(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'archive';
    }
}

if (!function_exists('is_category')) {
    function is_category(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'category';
    }
}

if (!function_exists('is_tag')) {
    function is_tag(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'tag';
    }
}

if (!function_exists('is_tax')) {
    function is_tax(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'taxonomy';
    }
}

if (!function_exists('is_author')) {
    function is_author(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'author';
    }
}

if (!function_exists('is_date')) {
    function is_date(): bool
    {
        return $GLOBALS['wp_stub_template'] === 'date';
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return $GLOBALS['wp_stub_logged_in'] ?? false;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        wp_stub_record('current_user_can', compact('capability', 'args'));

        return $GLOBALS['wp_stub_user_can'][$capability] ?? false;
    }
}

// ──────────────────────────────────────────────
// Query functions
// ──────────────────────────────────────────────

if (!function_exists('get_post_type')) {
    function get_post_type(): string|false
    {
        return $GLOBALS['wp_stub_post_type'] ?? 'post';
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object(): ?object
    {
        return $GLOBALS['wp_stub_queried_object'] ?? null;
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var(string $var, mixed $default = ''): mixed
    {
        return $GLOBALS['wp_stub_query_vars'][$var] ?? $default;
    }
}

// ──────────────────────────────────────────────
// Scripts & Styles
// ──────────────────────────────────────────────

if (!function_exists('wp_dequeue_style')) {
    function wp_dequeue_style(string $handle): void
    {
        wp_stub_record('wp_dequeue_style', compact('handle'));
    }
}

if (!function_exists('wp_dequeue_script')) {
    function wp_dequeue_script(string $handle): void
    {
        wp_stub_record('wp_dequeue_script', compact('handle'));
    }
}

// ──────────────────────────────────────────────
// Misc
// ──────────────────────────────────────────────

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return $GLOBALS['wp_stub_is_admin'] ?? false;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(): WP_User
    {
        return $GLOBALS['wp_stub_current_user'] ?? new WP_User();
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Default template state
$GLOBALS['wp_stub_template'] = 'index';
$GLOBALS['wp_stub_logged_in'] = false;
$GLOBALS['wp_stub_post_type'] = 'post';
$GLOBALS['wp_stub_queried_object'] = null;
$GLOBALS['wp_stub_query_vars'] = [];
$GLOBALS['wp_stub_is_admin'] = false;
$GLOBALS['wp_stub_user_can'] = [];
$GLOBALS['wp_stub_nav_menus'] = [];
