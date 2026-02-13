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
    $GLOBALS['wp_stub_options'] = [];
    $GLOBALS['wp_stub_attachments'] = [];
    $GLOBALS['wp_stub_post_meta'] = [];
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
        private bool $is_main = true;
        private array $query_vars = [];
        public array $posts = [];
        public int $post_count = 0;

        public function is_main_query(): bool
        {
            return $this->is_main;
        }

        public function set_main_query(bool $is_main): void
        {
            $this->is_main = $is_main;
        }

        public function get(string $key, mixed $default = ''): mixed
        {
            return $this->query_vars[$key] ?? $default;
        }

        public function set(string $key, mixed $value): void
        {
            $this->query_vars[$key] = $value;
        }

        public function get_query_vars(): array
        {
            return $this->query_vars;
        }
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

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params = [];

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function get_params(): array
        {
            return $this->params;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        public function has_param(string $key): bool
        {
            return isset($this->params[$key]);
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var array<string, string> */
        private array $headers = [];

        public function __construct(
            private mixed $data = null,
            private int $status = 200,
        ) {}

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }

        public function get_headers(): array
        {
            return $this->headers;
        }
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

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        wp_stub_record('do_action', compact('hook', 'args'));
    }
}

if (!function_exists('do_action_deprecated')) {
    function do_action_deprecated(string $hook, array $args, string $version, string $replacement = ''): void
    {
        wp_stub_record('do_action_deprecated', compact('hook', 'args', 'version', 'replacement'));
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

if (!function_exists('add_query_arg')) {
    function add_query_arg(array|string $key, mixed $value = null, ?string $url = null): string
    {
        // Simple implementation for testing
        if (is_array($key)) {
            $args = $key;
            $url = $value ?? $_SERVER['REQUEST_URI'] ?? '/';
        } else {
            $args = [$key => $value];
            $url = $url ?? $_SERVER['REQUEST_URI'] ?? '/';
        }

        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        parse_str($parsed['query'] ?? '', $existing);

        $merged = array_merge($existing, $args);
        $query = http_build_query($merged);

        return $query !== '' ? "{$path}?{$query}" : $path;
    }
}

if (!function_exists('remove_query_arg')) {
    function remove_query_arg(array|string $keys, ?string $url = null): string
    {
        $url = $url ?? $_SERVER['REQUEST_URI'] ?? '/';
        $keys = (array) $keys;

        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        parse_str($parsed['query'] ?? '', $existing);

        foreach ($keys as $key) {
            unset($existing[$key]);
        }

        $query = http_build_query($existing);

        return $query !== '' ? "{$path}?{$query}" : $path;
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

// ──────────────────────────────────────────────
// Scripts & Styles
// ──────────────────────────────────────────────

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(
        string $handle,
        string $src = '',
        array $deps = [],
        ?string $ver = null,
        string $media = 'all',
    ): void {
        wp_stub_record('wp_enqueue_style', compact('handle', 'src', 'deps', 'ver', 'media'));
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(
        string $handle,
        string $src = '',
        array $deps = [],
        ?string $ver = null,
        bool $in_footer = false,
    ): void {
        wp_stub_record('wp_enqueue_script', compact('handle', 'src', 'deps', 'ver', 'in_footer'));
    }
}

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
// Theme directories
// ──────────────────────────────────────────────

if (!function_exists('get_template_directory')) {
    function get_template_directory(): string
    {
        return $GLOBALS['wp_stub_template_directory'] ?? '/var/www/wp-content/themes/theme';
    }
}

if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri(): string
    {
        return $GLOBALS['wp_stub_template_directory_uri'] ?? 'http://example.com/wp-content/themes/theme';
    }
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory(): string
    {
        return $GLOBALS['wp_stub_stylesheet_directory'] ?? '/var/www/wp-content/themes/child-theme';
    }
}

if (!function_exists('get_stylesheet_directory_uri')) {
    function get_stylesheet_directory_uri(): string
    {
        return $GLOBALS['wp_stub_stylesheet_directory_uri'] ?? 'http://example.com/wp-content/themes/child-theme';
    }
}

// ──────────────────────────────────────────────
// Transients (Cache)
// ──────────────────────────────────────────────

if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        wp_stub_record('get_transient', compact('transient'));

        return $GLOBALS['wp_stub_transients'][$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        wp_stub_record('set_transient', compact('transient', 'value', 'expiration'));
        $GLOBALS['wp_stub_transients'][$transient] = $value;

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        wp_stub_record('delete_transient', compact('transient'));

        if (!isset($GLOBALS['wp_stub_transients'][$transient])) {
            return false;
        }

        unset($GLOBALS['wp_stub_transients'][$transient]);

        return true;
    }
}

// ──────────────────────────────────────────────
// Logging
// ──────────────────────────────────────────────

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', false);
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
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

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }
}

if (!function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

// ──────────────────────────────────────────────
// Timber stubs for testing
// ──────────────────────────────────────────────

$GLOBALS['wp_stub_timber_posts'] = [];
$GLOBALS['wp_stub_timber_terms'] = [];

/**
 * Set a mock Timber post for testing.
 *
 * @param int $id Post ID
 * @param \Timber\Post|null $post Post object or null
 */
function wp_stub_set_timber_post(int $id, ?\Timber\Post $post): void
{
    $GLOBALS['wp_stub_timber_posts'][$id] = $post;
}

/**
 * Set a mock Timber term for testing.
 *
 * @param string $key Term key (e.g., "id:5:category")
 * @param \Timber\Term|null $term Term object or null
 */
function wp_stub_set_timber_term(string $key, ?\Timber\Term $term): void
{
    $GLOBALS['wp_stub_timber_terms'][$key] = $term;
}

if (!function_exists('get_body_class')) {
    /**
     * @return list<string>
     */
    function get_body_class(): array
    {
        return $GLOBALS['wp_stub_body_class'] ?? [];
    }
}

if (!function_exists('wp_title')) {
    function wp_title(string $sep = '&raquo;', bool $display = true, string $seplocation = ''): string
    {
        return $GLOBALS['wp_stub_wp_title'] ?? '';
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return $GLOBALS['wp_stub_is_multisite'] ?? false;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw'): string
    {
        return match ($show) {
            'name' => $GLOBALS['wp_stub_bloginfo_name'] ?? 'Test Site',
            'description' => $GLOBALS['wp_stub_bloginfo_description'] ?? 'Just another WordPress site',
            'url', 'wpurl', 'siteurl' => $GLOBALS['wp_stub_bloginfo_url'] ?? 'http://example.com',
            'admin_email' => $GLOBALS['wp_stub_bloginfo_admin_email'] ?? 'admin@example.com',
            'charset' => 'UTF-8',
            'language' => 'en-US',
            'version' => '6.0',
            default => '',
        };
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '', ?string $scheme = null): string
    {
        $url = $GLOBALS['wp_stub_home_url'] ?? 'http://example.com';

        return $path ? rtrim($url, '/') . '/' . ltrim($path, '/') : $url;
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = '', ?string $scheme = null): string
    {
        $url = $GLOBALS['wp_stub_site_url'] ?? 'http://example.com';

        return $path ? rtrim($url, '/') . '/' . ltrim($path, '/') : $url;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['wp_stub_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value, string|bool|null $autoload = null): bool
    {
        wp_stub_record('update_option', compact('option', 'value', 'autoload'));
        $GLOBALS['wp_stub_options'][$option] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        wp_stub_record('delete_option', compact('option'));
        unset($GLOBALS['wp_stub_options'][$option]);

        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        wp_stub_record('apply_filters', compact('hook', 'value', 'args'));

        return $value;
    }
}

if (!function_exists('apply_filters_deprecated')) {
    function apply_filters_deprecated(string $hook, array $args, string $version, string $replacement = ''): mixed
    {
        wp_stub_record('apply_filters_deprecated', compact('hook', 'args', 'version', 'replacement'));

        return $args[0] ?? null;
    }
}

if (!function_exists('get_theme_support')) {
    function get_theme_support(string $feature): mixed
    {
        return $GLOBALS['wp_stub_theme_support'][$feature] ?? false;
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/\\') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string
    {
        return rtrim($value, '/\\');
    }
}

// ──────────────────────────────────────────────
// Attachments & Media
// ──────────────────────────────────────────────

if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url(int $attachmentId, string $size = 'thumbnail'): string|false
    {
        wp_stub_record('wp_get_attachment_image_url', compact('attachmentId', 'size'));

        return $GLOBALS['wp_stub_attachments'][$attachmentId]['url'] ?? false;
    }
}

if (!function_exists('wp_get_attachment_metadata')) {
    /**
     * @return array<string, mixed>|false
     */
    function wp_get_attachment_metadata(int $attachmentId): array|false
    {
        wp_stub_record('wp_get_attachment_metadata', compact('attachmentId'));

        return $GLOBALS['wp_stub_attachments'][$attachmentId]['meta'] ?? false;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
    {
        wp_stub_record('get_post_meta', compact('postId', 'key', 'single'));

        if ($key === '') {
            return $GLOBALS['wp_stub_post_meta'][$postId] ?? [];
        }

        $value = $GLOBALS['wp_stub_post_meta'][$postId][$key] ?? null;

        if ($single) {
            return $value ?? '';
        }

        return $value !== null ? [$value] : [];
    }
}

$GLOBALS['wp_stub_attachments'] = [];
$GLOBALS['wp_stub_post_meta'] = [];

// ──────────────────────────────────────────────
// Locale & i18n
// ──────────────────────────────────────────────

if (!function_exists('get_locale')) {
    function get_locale(): string
    {
        return $GLOBALS['wp_stub_locale'] ?? 'en_US';
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void
    {
        echo $text;
    }
}

if (!function_exists('_x')) {
    function _x(string $text, string $context, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        return $number === 1 ? $single : $plural;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!class_exists('WP_Theme')) {
    class WP_Theme
    {
        private array $data = [];

        public function __construct(string $theme_dir = '', string $theme_root = '')
        {
            $this->data = [
                'Name' => 'Test Theme',
                'Version' => '1.0.0',
                'ThemeURI' => 'http://example.com',
                'Description' => 'A test theme',
                'Author' => 'Test Author',
                'AuthorURI' => 'http://example.com',
                'TextDomain' => 'test-theme',
            ];
        }

        public function get(string $header): string
        {
            return $this->data[$header] ?? '';
        }

        public function get_stylesheet(): string
        {
            return 'test-theme';
        }

        public function get_template_directory_uri(): string
        {
            return 'http://example.com/wp-content/themes/test-theme';
        }

        public function parent(): ?WP_Theme
        {
            return null;
        }

        public function exists(): bool
        {
            return true;
        }
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme(?string $stylesheet = null): WP_Theme
    {
        return new WP_Theme();
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return false;
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
$GLOBALS['wp_stub_locale'] = 'en_US';
