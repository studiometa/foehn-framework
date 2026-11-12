<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Trace;

use Studiometa\Foehn\Errors\SourcePaths;
use Throwable;

/**
 * Traces each front-end request: what it ran, stored for `wp foehn trace`.
 *
 * When WordPress sends the headers of a front-end request (`send_headers`), the
 * request gets a short id, sent back in the `X-Foehn-Trace` header. From then on,
 * RequestTrace keeps the templates WordPress tries, the template file it finds, the
 * controller, the views, the context providers and the blocks. At shutdown, the trace
 * is written to the store with the status, the time and the number of queries.
 *
 * `send_headers` runs only in `WP::main()`: the admin, admin-ajax, WP-Cron, a REST
 * request and WP-CLI never send it, so they are not traced. The errors the error
 * recorder writes after it carry the id, and `wp foehn trace` shows them with the
 * trace.
 */
final class RequestTracer
{
    public const string HEADER = 'X-Foehn-Trace';

    /**
     * The template types WordPress looks up, each with its `{type}_template_hierarchy`
     * filter. See get_query_template().
     *
     * @var list<string>
     */
    private const array TEMPLATE_TYPES = [
        '404',
        'search',
        'frontpage',
        'home',
        'privacypolicy',
        'embed',
        'taxonomy',
        'attachment',
        'single',
        'page',
        'singular',
        'category',
        'tag',
        'author',
        'date',
        'archive',
        'paged',
        'index',
    ];

    /** The characters of the URL a trace keeps. */
    private const int MAX_URL = 2000;

    private bool $started = false;

    public function __construct(
        private readonly RequestTrace $trace,
        private readonly TraceStore $store,
        private readonly SourcePaths $paths,
    ) {}

    /**
     * Begin listening. Calling it again does nothing.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;

        add_action('send_headers', $this->onSendHeaders(...));

        foreach (self::TEMPLATE_TYPES as $type) {
            add_filter("{$type}_template_hierarchy", $this->onTemplateHierarchy(...), PHP_INT_MAX);
        }

        // Before a template controller (priority 5) replaces the file with its output.
        add_filter('template_include', $this->onTemplateInclude(...), 0);
        add_filter('render_block', $this->onRenderBlock(...), 10, 2);
        $this->trace->listen();

        register_shutdown_function($this->shutdown(...));
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * `send_headers`: give the request its id and send it back in a header.
     */
    public function onSendHeaders(): void
    {
        if ($this->trace->isTracing()) {
            return;
        }

        $id = TraceStore::newId();
        $this->trace->begin($id);

        if (!headers_sent()) {
            header(self::HEADER . ': ' . $id);
        }
    }

    /**
     * `{type}_template_hierarchy`: the template files WordPress is about to look for.
     */
    public function onTemplateHierarchy(mixed $templates): mixed
    {
        if (is_array($templates)) {
            $this->trace->tryTemplates(array_values(array_filter($templates, is_string(...))));
        }

        return $templates;
    }

    /**
     * `template_include`: the template file WordPress found.
     */
    public function onTemplateInclude(mixed $template): mixed
    {
        if (is_string($template)) {
            $this->trace->useTemplateFile($template === '' ? '' : $this->paths->relative($template));
        }

        return $template;
    }

    /**
     * `render_block`: a block WordPress rendered. The markup goes back unchanged.
     */
    public function onRenderBlock(mixed $content, mixed $block = []): mixed
    {
        if (is_array($block) && is_string($block['blockName'] ?? null)) {
            $this->trace->renderBlock($block['blockName']);
        }

        return $content;
    }

    /**
     * The shutdown function: store the trace of a traced request.
     *
     * Like the error recorder, it must leave the request as it was: its own warnings
     * are silenced and an exception it throws is dropped.
     */
    public function shutdown(): void
    {
        $id = $this->trace->id();

        if ($id === null) {
            return;
        }

        set_error_handler(static fn(): bool => true);

        try {
            $this->store->save($this->build($id));
        } catch (Throwable) {
            return;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The trace of the request, as the store keeps it.
     *
     * @return array<string, mixed>
     */
    public function build(string $id): array
    {
        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : null;
        $status = http_response_code();
        $wpdb = $GLOBALS['wpdb'] ?? null;

        return [
            'id' => $id,
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'method' => is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : null,
            'url' => $uri === null ? null : mb_substr($uri, 0, self::MAX_URL),
            'status' => is_int($status) ? $status : null,
            'durationMs' => is_float($start) ? (int) round((microtime(true) - $start) * 1000) : null,
            'queries' => is_object($wpdb) && is_int($wpdb->num_queries ?? null) ? $wpdb->num_queries : null,
            ...$this->trace->toArray(),
        ];
    }
}
