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

// Registered filter callbacks, by hook then priority.
$GLOBALS['wp_stub_filters'] = [];

function wp_stub_reset(): void
{
    $GLOBALS['wp_stub_calls'] = [];
    // Registered filter callbacks run for real, so they have to be cleared: one left
    // behind would rewrite a value in a later, unrelated test.
    $GLOBALS['wp_stub_filters'] = [];
    $GLOBALS['wp_stub_logged_in'] = false;
    $GLOBALS['wp_stub_user_can'] = [];
    $GLOBALS['wp_stub_acf_fields'] = [];
    $GLOBALS['wp_stub_acf_field_objects'] = [];
    $GLOBALS['wp_stub_options'] = [];
    $GLOBALS['wp_stub_attachments'] = [];
    $GLOBALS['wp_stub_post_meta'] = [];
    $GLOBALS['wp_stub_post_fields'] = [];
    if (!function_exists('wp_get_environment_type')) {
        function wp_get_environment_type(): string
        {
            return $GLOBALS['wp_stub_environment_type'] ?? 'production';
        }
    }

    $GLOBALS['wp_stub_as_has_scheduled'] = [];

    // The page cache asks a dozen template conditionals whether this request is an
    // ordinary page. A leaked `true` would make an eligibility test pass for the
    // wrong reason, which is the one kind of green this feature cannot afford.
    $GLOBALS['wp_stub_conditionals'] = [];
    $GLOBALS['wp_stub_is_admin'] = false;
    $GLOBALS['wp_stub_template'] = 'index';
    $GLOBALS['wp_stub_posts'] = [];
    $GLOBALS['wp_stub_comments'] = [];
    $GLOBALS['wp_stub_permalinks'] = [];
    $GLOBALS['wp_stub_archive_links'] = [];
    $GLOBALS['wp_stub_author_slugs'] = [];
    $GLOBALS['wp_stub_object_taxonomies'] = [];
    $GLOBALS['wp_stub_post_terms'] = [];
    $GLOBALS['wp_stub_post_ancestors'] = [];
    $GLOBALS['wp_stub_adjacent_posts'] = [];
    $GLOBALS['wp_stub_sitemap_urls'] = [];
    unset($GLOBALS['wp_stub_sitemap_providers'], $GLOBALS['wp_stub_remote_status'], $GLOBALS['wp_stub_remote_error']);

    // Theme paths fall back to their stub defaults, so a test that points them at
    // a fixture directory cannot leak that into the next one.
    unset($GLOBALS['wp_stub_template_directory'], $GLOBALS['wp_stub_template_directory_uri']);
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
// WordPress constants
// ──────────────────────────────────────────────

if (!defined('WP_CONTENT_DIR')) {
    // Under a `web/` directory, as a real install has it: code that walks up from
    // wp-content to the project root then lands in the test directory rather than in
    // the system temp directory itself.
    define('WP_CONTENT_DIR', sys_get_temp_dir() . '/foehn-tests/web/wp-content');
}

if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', 'http://example.com/wp-content');
}

if (!defined('ABSPATH')) {
    // WordPress in a subdirectory, as this layout installs it: the document root is the
    // directory above ABSPATH, which is what the generated server snippets derive.
    define('ABSPATH', dirname(WP_CONTENT_DIR) . '/wp/');
}

if (!defined('WP_HOME')) {
    // The page cache validates a request's Host header against this rather than
    // trusting it, so a test that keys a request needs the site's own host to exist.
    // The same value `home_url()` falls back to, so the two never disagree.
    define('WP_HOME', 'http://example.com');
}

if (!defined('TIMBER_LOADED')) {
    // Keep Timber out of the unit suite, which it has always been in practice — and now
    // has to be said out loud. `Timber::init()` returns early unless `ABSPATH` is defined
    // *and* a `WP` class exists, and until now no run of this suite had both: the `WP`
    // stub arrived with one change and `ABSPATH` with another. Together they open the gate,
    // and Timber then calls `get_home_url()`, which nothing here stubs — fifteen Kernel and
    // view-engine tests failed on a function neither change went anywhere near.
    //
    // Stubbing that one function would let Timber initialise for real, pulling Twig and the
    // integration layer into tests that mock WordPress with plain functions. Declaring it
    // loaded keeps the boundary where it was.
    define('TIMBER_LOADED', true);
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

/**
 * WP-CLI output recorder.
 *
 * WpCli is final, so a command under test cannot be handed a double: it writes
 * through the real WpCli into this stub, and tests read the recorded calls.
 * error() records instead of exiting, so a failing command can be asserted on.
 */
if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static function log(string $message): void
        {
            wp_stub_record('wp_cli_log', compact('message'));
        }

        public static function success(string $message): void
        {
            wp_stub_record('wp_cli_success', compact('message'));
        }

        public static function error(string $message, bool $exit = true): void
        {
            wp_stub_record('wp_cli_error', compact('message', 'exit'));
        }

        public static function warning(string $message): void
        {
            wp_stub_record('wp_cli_warning', compact('message'));
        }

        public static function line(string $message = ''): void
        {
            wp_stub_record('wp_cli_line', compact('message'));
        }

        public static function colorize(string $string): string
        {
            return $string;
        }

        public static function add_command(string $name, mixed $callback, array $args = []): void
        {
            wp_stub_record('wp_cli_add_command', compact('name', 'callback', 'args'));
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
        public string $post_status = 'publish';
        public int $post_author = 1;
        public string $post_date = '2026-08-19 12:00:00';
    }
}

if (!class_exists('WP_Block')) {
    class WP_Block
    {
        /**
         * @param array<string, mixed> $attributes
         * @param array<int, mixed> $inner_blocks
         */
        /**
         * @param array<string, mixed> $context Block context, which is what a
         *   binding source asked for through `uses_context`
         */
        public function __construct(
            public array $attributes = [],
            public string $name = '',
            public array $inner_blocks = [],
            public array $context = [],
        ) {}
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

        $GLOBALS['wp_stub_filters'][$hook][$priority][] = $callback;
        ksort($GLOBALS['wp_stub_filters'][$hook]);
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

if (!function_exists('register_meta')) {
    /**
     * @param array<string, mixed> $args
     */
    function register_meta(string $objectType, string $metaKey, array $args = []): bool
    {
        wp_stub_record('register_meta', compact('objectType', 'metaKey', 'args'));

        return true;
    }
}

// ──────────────────────────────────────────────
// Rewrite rules
// ──────────────────────────────────────────────

if (!class_exists('WP')) {
    class WP
    {
        /** @var array<string, mixed> */
        public array $query_vars = [];
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule(string $regex, string|array $query, string $after = 'bottom'): void
    {
        wp_stub_record('add_rewrite_rule', compact('regex', 'query', 'after'));
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(bool $hard = true): void
    {
        wp_stub_record('flush_rewrite_rules', compact('hard'));
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

if (!function_exists('wp_register_style')) {
    function wp_register_style(
        string $handle,
        string $src = '',
        array $deps = [],
        ?string $ver = null,
        string $media = 'all',
    ): bool {
        wp_stub_record('wp_register_style', compact('handle', 'src', 'deps', 'ver', 'media'));

        return true;
    }
}

if (!function_exists('wp_register_script_module')) {
    function wp_register_script_module(
        string $id,
        string $src = '',
        array $deps = [],
        string|bool|null $version = false,
        array $args = [],
    ): void {
        wp_stub_record('wp_register_script_module', compact('id', 'src', 'deps', 'version', 'args'));
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script(
        string $handle,
        string $src = '',
        array $deps = [],
        ?string $ver = null,
        bool $inFooter = false,
    ): bool {
        wp_stub_record('wp_register_script', compact('handle', 'src', 'deps', 'ver', 'inFooter'));

        return true;
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

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script(string $handle, string $data, string $position = 'after'): bool
    {
        wp_stub_record('wp_add_inline_script', compact('handle', 'data', 'position'));

        return true;
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

if (!function_exists('get_theme_file_path')) {
    function get_theme_file_path(string $file = ''): string
    {
        $directory = $GLOBALS['wp_stub_template_directory'] ?? '/var/www/wp-content/themes/theme';

        return $file === '' ? $directory : $directory . '/' . ltrim($file, '/');
    }
}

if (!function_exists('get_theme_file_uri')) {
    function get_theme_file_uri(string $file = ''): string
    {
        $uri = $GLOBALS['wp_stub_template_directory_uri'] ?? 'http://example.com/wp-content/themes/theme';

        return $file === '' ? $uri : $uri . '/' . ltrim($file, '/');
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

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
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
// Block bindings
// ──────────────────────────────────────────────

if (!function_exists('get_post_field')) {
    function get_post_field(string $field, int|WP_Post $post = 0, string $context = 'display'): string
    {
        $id = $post instanceof WP_Post ? $post->ID : $post;

        return (string) ($GLOBALS['wp_stub_post_fields'][$id][$field] ?? '');
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
    {
        $text = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);

        return trim($text);
    }
}

if (!function_exists('register_block_bindings_source')) {
    /**
     * @param array<string, mixed> $sourceProperties
     */
    function register_block_bindings_source(string $sourceName, array $sourceProperties): void
    {
        wp_stub_record('register_block_bindings_source', compact('sourceName', 'sourceProperties'));
    }
}

// ──────────────────────────────────────────────
// Settings API
// ──────────────────────────────────────────────

if (!function_exists('register_setting')) {
    /**
     * @param array<string, mixed> $args
     */
    function register_setting(string $optionGroup, string $optionName, array $args = []): void
    {
        wp_stub_record('register_setting', compact('optionGroup', 'optionName', 'args'));
    }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        ?callable $callback = null,
        string $icon = '',
        int|float|null $position = null,
    ): string {
        wp_stub_record('add_menu_page', compact(
            'pageTitle',
            'menuTitle',
            'capability',
            'menuSlug',
            'callback',
            'icon',
            'position',
        ));

        return 'toplevel_page_' . $menuSlug;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page(
        string $parentSlug,
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $menuSlug,
        ?callable $callback = null,
        int|float|null $position = null,
    ): string|false {
        wp_stub_record('add_submenu_page', compact(
            'parentSlug',
            'pageTitle',
            'menuTitle',
            'capability',
            'menuSlug',
            'callback',
            'position',
        ));

        return $parentSlug . '_page_' . $menuSlug;
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields(string $optionGroup): void
    {
        wp_stub_record('settings_fields', compact('optionGroup'));

        echo '<input type="hidden" name="option_page" value="' . esc_attr($optionGroup) . '" />';
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections(string $page): void
    {
        wp_stub_record('do_settings_sections', compact('page'));
    }
}

if (!function_exists('settings_errors')) {
    function settings_errors(): void
    {
        wp_stub_record('settings_errors', []);
    }
}

if (!function_exists('submit_button')) {
    function submit_button(?string $text = null): void
    {
        wp_stub_record('submit_button', compact('text'));

        echo '<button type="submit">' . esc_html($text ?? 'Save Changes') . '</button>';
    }
}

if (!function_exists('rest_sanitize_boolean')) {
    function rest_sanitize_boolean(mixed $value): bool
    {
        if (is_string($value)) {
            return !in_array(strtolower($value), ['', '0', 'false', 'off', 'no'], true);
        }

        return (bool) $value;
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

if (!function_exists('content_url')) {
    function content_url(string $path = ''): string
    {
        $url = $GLOBALS['wp_stub_content_url'] ?? WP_CONTENT_URL;

        return $path ? rtrim($url, '/') . '/' . ltrim($path, '/') : $url;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($data, $flags, $depth);
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

        // Callbacks registered through add_filter() actually run, as they do in
        // WordPress. A stub that recorded the call and returned the value unchanged
        // could not test a filter seam at all — the code under test would look wired
        // up while nothing it offered was reachable.
        foreach ($GLOBALS['wp_stub_filters'][$hook] ?? [] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value, ...$args);
            }
        }

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

// ──────────────────────────────────────────────
// Action Scheduler stubs
// ──────────────────────────────────────────────

if (!function_exists('as_schedule_single_action')) {
    function as_schedule_single_action(
        int $timestamp,
        string $hook,
        array $args = [],
        string $group = '',
        bool $unique = false,
    ): int {
        wp_stub_record('as_schedule_single_action', compact('timestamp', 'hook', 'args', 'group', 'unique'));

        return random_int(1, 99999);
    }
}

if (!function_exists('as_schedule_recurring_action')) {
    function as_schedule_recurring_action(
        int $timestamp,
        int $intervalInSeconds,
        string $hook,
        array $args = [],
        string $group = '',
        bool $unique = false,
    ): int {
        wp_stub_record('as_schedule_recurring_action', compact(
            'timestamp',
            'intervalInSeconds',
            'hook',
            'args',
            'group',
            'unique',
        ));

        return random_int(1, 99999);
    }
}

if (!function_exists('as_has_scheduled_action')) {
    function as_has_scheduled_action(string $hook, ?array $args = null, string $group = ''): bool
    {
        wp_stub_record('as_has_scheduled_action', compact('hook', 'args', 'group'));

        return $GLOBALS['wp_stub_as_has_scheduled'][$hook] ?? false;
    }
}

if (!function_exists('as_unschedule_all_actions')) {
    function as_unschedule_all_actions(string $hook, ?array $args = null, string $group = ''): void
    {
        wp_stub_record('as_unschedule_all_actions', compact('hook', 'args', 'group'));
    }
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type(): string
    {
        return $GLOBALS['wp_stub_environment_type'] ?? 'production';
    }
}

// ──────────────────────────────────────────────
// Page cache: request context and purge targets
// ──────────────────────────────────────────────

/**
 * The template conditionals the page cache asks about, all off by default.
 *
 * A stub that answered `true` here would make every eligibility test pass for the
 * wrong reason, so the default is the state of an ordinary front-end page request.
 */
$GLOBALS['wp_stub_conditionals'] = [];

function wp_stub_set_conditional(string $name, bool $value): void
{
    $GLOBALS['wp_stub_conditionals'][$name] = $value;
}

function wp_stub_conditional(string $name): bool
{
    return $GLOBALS['wp_stub_conditionals'][$name] ?? false;
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return wp_stub_conditional('wp_doing_ajax');
    }
}

if (!function_exists('wp_doing_cron')) {
    function wp_doing_cron(): bool
    {
        return wp_stub_conditional('wp_doing_cron');
    }
}

if (!function_exists('is_feed')) {
    function is_feed(mixed $feeds = ''): bool
    {
        return wp_stub_conditional('is_feed');
    }
}

if (!function_exists('is_trackback')) {
    function is_trackback(): bool
    {
        return wp_stub_conditional('is_trackback');
    }
}

if (!function_exists('is_robots')) {
    function is_robots(): bool
    {
        return wp_stub_conditional('is_robots');
    }
}

if (!function_exists('is_embed')) {
    function is_embed(): bool
    {
        return wp_stub_conditional('is_embed');
    }
}

if (!function_exists('is_preview')) {
    function is_preview(): bool
    {
        return wp_stub_conditional('is_preview');
    }
}

if (!function_exists('is_customize_preview')) {
    function is_customize_preview(): bool
    {
        return wp_stub_conditional('is_customize_preview');
    }
}

if (!function_exists('post_password_required')) {
    function post_password_required(mixed $post = null): bool
    {
        return wp_stub_conditional('post_password_required');
    }
}

if (!function_exists('get_post')) {
    function get_post(mixed $post = null, string $output = 'OBJECT'): ?WP_Post
    {
        if ($post instanceof WP_Post) {
            return $post;
        }

        return $GLOBALS['wp_stub_posts'][(int) $post] ?? null;
    }
}

if (!function_exists('get_comment')) {
    function get_comment(mixed $comment = null, string $output = 'OBJECT'): ?object
    {
        return $GLOBALS['wp_stub_comments'][(int) $comment] ?? null;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink(mixed $post = 0, bool $leavename = false): string|false
    {
        $id = $post instanceof WP_Post ? $post->ID : (int) $post;
        $name = $post instanceof WP_Post ? $post->post_name : $GLOBALS['wp_stub_posts'][$id]->post_name ?? '';

        return $GLOBALS['wp_stub_permalinks'][$id] ?? home_url('/' . $name . '/');
    }
}

if (!function_exists('get_post_type_archive_link')) {
    function get_post_type_archive_link(string $postType): string|false
    {
        return $GLOBALS['wp_stub_archive_links'][$postType] ?? false;
    }
}

if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url(int $authorId, string $authorNicename = ''): string
    {
        return home_url('/author/' . ($GLOBALS['wp_stub_author_slugs'][$authorId] ?? $authorId) . '/');
    }
}

if (!function_exists('get_month_link')) {
    function get_month_link(int $year, int $month): string
    {
        return home_url(sprintf('/%04d/%02d/', $year, $month));
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies(mixed $object, string $output = 'names'): array
    {
        $type = is_string($object) ? $object : $object->post_type ?? 'post';

        return $GLOBALS['wp_stub_object_taxonomies'][$type] ?? [];
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms(int $postId, mixed $taxonomy = 'post_tag', array $args = []): array
    {
        return $GLOBALS['wp_stub_post_terms'][$postId][(string) $taxonomy] ?? [];
    }
}

if (!function_exists('get_post_ancestors')) {
    function get_post_ancestors(mixed $post): array
    {
        $id = $post instanceof WP_Post ? $post->ID : (int) $post;

        return $GLOBALS['wp_stub_post_ancestors'][$id] ?? [];
    }
}

if (!function_exists('get_adjacent_post')) {
    function get_adjacent_post(
        bool $inSameTerm = false,
        string $excludedTerms = '',
        bool $previous = true,
        string $taxonomy = 'category',
    ): mixed {
        $id = $GLOBALS['post'] instanceof WP_Post ? $GLOBALS['post']->ID : 0;

        return $GLOBALS['wp_stub_adjacent_posts'][$id][$previous ? 'previous' : 'next'] ?? null;
    }
}

if (!function_exists('get_term_link')) {
    function get_term_link(mixed $term, string $taxonomy = ''): string|false
    {
        $slug = $term instanceof WP_Term ? $term->slug : (string) $term;
        $taxonomy = $term instanceof WP_Term && $term->taxonomy !== '' ? $term->taxonomy : $taxonomy;

        if ($slug === '') {
            return false;
        }

        return home_url('/' . ($taxonomy !== '' ? $taxonomy . '/' : '') . $slug . '/');
    }
}

$GLOBALS['wp_stub_posts'] = [];
$GLOBALS['wp_stub_comments'] = [];
$GLOBALS['wp_stub_permalinks'] = [];
$GLOBALS['wp_stub_archive_links'] = [];
$GLOBALS['wp_stub_author_slugs'] = [];
$GLOBALS['wp_stub_object_taxonomies'] = [];
$GLOBALS['wp_stub_post_terms'] = [];
$GLOBALS['wp_stub_post_ancestors'] = [];
$GLOBALS['wp_stub_adjacent_posts'] = [];

// ──────────────────────────────────────────────
// HTTP and sitemaps, for the page cache warmer
// ──────────────────────────────────────────────

if (!function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): array|WP_Error
    {
        wp_stub_record('wp_remote_get', compact('url', 'args'));

        if ($GLOBALS['wp_stub_remote_error'] ?? false) {
            return new WP_Error('http_request_failed', 'stubbed failure');
        }

        return ['response' => ['code' => $GLOBALS['wp_stub_remote_status'] ?? 200]];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(mixed $response): int|string
    {
        return is_array($response) ? $response['response']['code'] ?? 0 : 0;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_get_sitemap_providers')) {
    function wp_get_sitemap_providers(): array
    {
        if (isset($GLOBALS['wp_stub_sitemap_providers'])) {
            return $GLOBALS['wp_stub_sitemap_providers'];
        }

        return [
            new class {
                public function get_sitemap_type_data(): array
                {
                    return [['name' => 'post', 'pages' => 1]];
                }

                public function get_url_list(int $page, string $type = ''): array
                {
                    return $GLOBALS['wp_stub_sitemap_urls'] ?? [];
                }
            },
        ];
    }
}

$GLOBALS['wp_stub_sitemap_urls'] = [];

$GLOBALS['wp_stub_as_has_scheduled'] = [];

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
$GLOBALS['wp_stub_environment_type'] = 'production';
$GLOBALS['wp_stub_post_fields'] = [];
