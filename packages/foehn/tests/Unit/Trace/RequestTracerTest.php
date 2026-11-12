<?php

declare(strict_types=1);

use Studiometa\Foehn\Errors\SourcePaths;
use Studiometa\Foehn\Trace\RequestTrace;
use Studiometa\Foehn\Trace\RequestTracer;
use Studiometa\Foehn\Trace\TraceStore;

describe('RequestTracer', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->directory = errorLogDirectory();
        $this->store = new TraceStore($this->directory);
        $this->trace = new RequestTrace();
        $this->tracer = new RequestTracer($this->trace, $this->store, new SourcePaths('/srv/site/theme'));
        $this->server = $_SERVER;
    });

    afterEach(function () {
        $_SERVER = $this->server;
        unset($GLOBALS['wpdb']);
        removeErrorLogDirectory($this->directory);
    });

    it('starts once, and listens to the headers, the templates, the views and the blocks', function () {
        // This trace never gets an id, so the shutdown function it registers writes nothing.
        $this->tracer->start();
        $this->tracer->start();

        $hooks = [
            ...array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook'),
            ...array_column(array_column(wp_stub_get_calls('add_filter'), 'args'), 'hook'),
        ];

        expect($this->tracer->isStarted())->toBeTrue();
        expect($hooks)->toContain(
            'send_headers',
            'single_template_hierarchy',
            'index_template_hierarchy',
            'template_include',
            'render_block',
            'timber/loader/render_data',
        );
        expect(array_count_values($hooks)['send_headers'])->toBe(1);
    });

    it('gives the request its id when WordPress sends the headers, once', function () {
        expect($this->trace->id())->toBeNull();

        $this->tracer->onSendHeaders();
        $id = $this->trace->id();
        $this->tracer->onSendHeaders();

        expect($id)->toMatch(TraceStore::ID_PATTERN);
        expect($this->trace->id())->toBe($id);
    });

    it('keeps the templates tried, the file found and the blocks, and changes none of them', function () {
        $this->tracer->onSendHeaders();

        expect($this->tracer->onTemplateHierarchy(['single-post.php', 'single.php']))->toBe([
            'single-post.php',
            'single.php',
        ]);
        expect($this->tracer->onTemplateHierarchy('not a list'))->toBe('not a list');
        expect($this->tracer->onTemplateInclude('/srv/site/theme/index.php'))->toBe('/srv/site/theme/index.php');
        expect($this->tracer->onRenderBlock('<p>Hi</p>', ['blockName' => 'core/paragraph']))->toBe('<p>Hi</p>');
        expect($this->tracer->onRenderBlock("\n", ['blockName' => null]))->toBe("\n");

        $trace = $this->trace->toArray();

        expect($trace['hierarchy'])->toBe(['single-post.php', 'single.php']);
        expect($trace['templateFile'])->toBe('theme/index.php');
        expect($trace['blocks'])->toBe(['core/paragraph' => 1]);
    });

    it('builds the trace with the request, its status, its time and its queries', function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hello-world/?preview=1';
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 0.25;
        $GLOBALS['wpdb'] = new class {
            public int $num_queries = 31;
        };
        $this->trace->matchTemplate('single', 'App\\Controllers\\SingleController');

        $trace = $this->tracer->build('a1b2c3');

        expect(array_keys($trace))->toBe([
            'id',
            'time',
            'method',
            'url',
            'status',
            'durationMs',
            'queries',
            'hierarchy',
            'templateFile',
            'template',
            'controller',
            'views',
            'candidates',
            'providers',
            'blocks',
        ]);
        expect($trace['id'])->toBe('a1b2c3');
        expect($trace['method'])->toBe('GET');
        expect($trace['url'])->toBe('/hello-world/?preview=1');
        expect($trace['durationMs'])->toBeGreaterThanOrEqual(250);
        expect($trace['queries'])->toBe(31);
        expect($trace['controller'])->toBe('App\\Controllers\\SingleController');
    });

    it('stores the trace at shutdown, only for a request with an id', function () {
        $this->tracer->shutdown();

        expect($this->store->last())->toBeNull();

        $this->trace->begin('a1b2c3');
        $this->tracer->shutdown();

        expect($this->store->find('a1b2c3')['id'] ?? null)->toBe('a1b2c3');
    });

    it('stays silent when it cannot write the store', function () {
        $file = tempnam(sys_get_temp_dir(), 'foehn-trace-');
        $tracer = new RequestTracer($this->trace, new TraceStore($file . '/traces'), new SourcePaths('/srv'));
        $this->trace->begin('a1b2c3');
        $raised = [];
        set_error_handler(static function (int $type, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });

        try {
            $tracer->shutdown();
        } finally {
            restore_error_handler();
            unlink($file);
        }

        expect($raised)->toBe([]);
    });
});
