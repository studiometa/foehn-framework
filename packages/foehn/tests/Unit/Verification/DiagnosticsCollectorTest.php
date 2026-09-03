<?php

declare(strict_types=1);

use Studiometa\Foehn\Verification\Updates\DiagnosticsCollector;

/**
 * Fire a hook the way WordPress would, through the callbacks the collector registered.
 *
 * Going through the recorded `add_action()` calls rather than calling the methods
 * directly is what makes these tests prove the wiring as well as the recording: a
 * collector that recorded perfectly but registered nothing would pass otherwise.
 */
function dispatchToCollector(string $hook, mixed ...$args): void
{
    foreach (wp_stub_get_calls('add_action') as $call) {
        if ($call['args']['hook'] !== $hook) {
            continue;
        }

        $call['args']['callback'](...$args);
    }
}

/**
 * A previous error handler that records what it was given and answers what it is told to.
 */
function collectorHandlerSpy(): object
{
    return new class {
        /** @var list<array{int, string, string, int}> */
        public array $calls = [];

        public bool $verdict = false;

        public function __invoke(int $errno, string $message, string $file = '', int $line = 0): bool
        {
            $this->calls[] = [$errno, $message, $file, $line];

            return $this->verdict;
        }
    };
}

/**
 * The one recorded item of a given type.
 *
 * @return array<string, mixed>
 */
function collectorItem(DiagnosticsCollector $collector, string $type): array
{
    foreach ($collector->diagnostics() as $item) {
        if ($item['type'] === $type) {
            return $item;
        }
    }

    return [];
}

beforeEach(function () {
    wp_stub_reset();

    // The suite runs with failOnWarning, so PHPUnit's own error handler must never see
    // what these tests hand the collector: a spy is installed first, and start() then
    // captures the spy as the handler to delegate to. afterEach unwinds both, in order,
    // so the handler stack is left exactly as it was found.
    $this->spy = collectorHandlerSpy();
    set_error_handler($this->spy);

    $this->collector = new DiagnosticsCollector();
    $this->collector->start();
});

afterEach(function () {
    $this->collector->stop();
    restore_error_handler();
});

describe('DiagnosticsCollector: the four sources', function () {
    it('records a PHP error handled through set_error_handler()', function () {
        $this->collector->handleError(E_USER_DEPRECATED, 'strlen(): Passing null is deprecated', '/x/theme.php', 12);

        expect(collectorItem($this->collector, 'php_error'))->toMatchArray([
            'type' => 'php_error',
            'symbol' => 'E_USER_DEPRECATED',
            'message' => 'strlen(): Passing null is deprecated',
            'line' => 12,
            'count' => 1,
        ]);
    });

    it('records deprecated_function_run', function () {
        dispatchToCollector('deprecated_function_run', 'old_function', 'new_function()', '7.0.0');

        expect(collectorItem($this->collector, 'deprecated_function'))->toMatchArray([
            'symbol' => 'old_function',
            'message' => 'Use new_function() instead.',
            'version' => '7.0.0',
            'count' => 1,
        ]);
    });

    it('records deprecated_hook_run', function () {
        dispatchToCollector('deprecated_hook_run', 'old_hook', 'new_hook', '7.0.0', 'Rename the callback.');

        expect(collectorItem($this->collector, 'deprecated_hook'))->toMatchArray([
            'symbol' => 'old_hook',
            'message' => 'Use new_hook instead. Rename the callback.',
            'version' => '7.0.0',
        ]);
    });

    it('records doing_it_wrong_run', function () {
        dispatchToCollector('doing_it_wrong_run', 'wp_enqueue_script', 'Called too early.', '3.3.0');

        expect(collectorItem($this->collector, 'doing_it_wrong'))->toMatchArray([
            'symbol' => 'wp_enqueue_script',
            'message' => 'Called too early.',
            'version' => '3.3.0',
        ]);
    });

    it('says where a hook-based diagnostic came from, since the hook does not', function () {
        dispatchToCollector('doing_it_wrong_run', 'wp_enqueue_script', 'Called too early.', '3.3.0');

        $item = collectorItem($this->collector, 'doing_it_wrong');

        // This file: the first frame that is neither WordPress core nor the collector.
        expect($item['file'])->toEndWith('DiagnosticsCollectorTest.php');
        expect($item['file'])->not->toStartWith('/');
        expect($item['line'])->toBeGreaterThan(0);
    });
});

describe('DiagnosticsCollector: collection changes nothing', function () {
    it('calls the handler that was installed before it', function () {
        $this->collector->handleError(E_USER_WARNING, 'injected', '/x/y.php', 3);

        expect($this->spy->calls)->toBe([[E_USER_WARNING, 'injected', '/x/y.php', 3]]);
    });

    it('passes the previous handler’s verdict through rather than deciding for it', function () {
        expect($this->collector->handleError(E_USER_WARNING, 'php handles this one'))->toBeFalse();

        $this->spy->verdict = true;

        expect($this->collector->handleError(E_USER_WARNING, 'the previous handler took this one'))->toBeTrue();
    });

    it('returns false when nothing was handling errors, so PHP still handles them', function () {
        // set_error_handler(null) restores PHP's own handling; the collector installed on
        // top of that has no previous handler to delegate to.
        set_error_handler(null);

        try {
            $collector = new DiagnosticsCollector();
            $collector->start();

            try {
                expect($collector->handleError(E_USER_NOTICE, 'nothing above PHP'))->toBeFalse();
            } finally {
                $collector->stop();
            }
        } finally {
            restore_error_handler();
        }
    });

    it('starts once, so one error is recorded once', function () {
        $this->collector->start();
        $this->collector->handleError(E_USER_WARNING, 'injected', '/x/y.php', 3);

        expect($this->collector->diagnostics())->toHaveCount(1);
        expect($this->spy->calls)->toHaveCount(1);
    });
});

describe('DiagnosticsCollector: the report shape', function () {
    it('counts a repeat instead of repeating it', function () {
        for ($i = 0; $i < 3; $i++) {
            $this->collector->handleError(E_USER_DEPRECATED, 'same message', '/x/y.php', 12);
        }

        expect($this->collector->diagnostics())->toHaveCount(1);
        expect(collectorItem($this->collector, 'php_error')['count'])->toBe(3);
    });

    it('keeps two diagnostics that differ only by line apart', function () {
        $this->collector->handleError(E_USER_DEPRECATED, 'same message', '/x/y.php', 12);
        $this->collector->handleError(E_USER_DEPRECATED, 'same message', '/x/y.php', 13);

        expect($this->collector->diagnostics())->toHaveCount(2);
    });

    it('reports a path relative to the install rather than an absolute one', function () {
        $file = constant('WP_CONTENT_DIR') . '/plugins/example/plugin.php';

        $this->collector->handleError(E_USER_DEPRECATED, 'message', $file, 42);

        expect(collectorItem($this->collector, 'php_error')['file'])->toBe('wp-content/plugins/example/plugin.php');
    });

    it('reports a core path relative to ABSPATH', function () {
        $this->collector->handleError(E_DEPRECATED, 'message', constant('ABSPATH') . 'wp-includes/post.php', 7);

        expect(collectorItem($this->collector, 'php_error')['file'])->toBe('wp-includes/post.php');
    });

    it('reports a file it can place nowhere by name alone', function () {
        $this->collector->handleError(E_USER_DEPRECATED, 'message', '/opt/elsewhere/thing.php', 1);

        expect(collectorItem($this->collector, 'php_error')['file'])->toBe('thing.php');
    });

    it('orders diagnostics the same way whatever order they arrived in', function () {
        $second = new DiagnosticsCollector();

        set_error_handler($this->spy);

        try {
            $second->start();

            try {
                $this->collector->handleError(E_USER_DEPRECATED, 'zebra', '/x/y.php', 1);
                $this->collector->handleError(E_USER_DEPRECATED, 'apple', '/x/y.php', 2);

                $second->handleError(E_USER_DEPRECATED, 'apple', '/x/y.php', 2);
                $second->handleError(E_USER_DEPRECATED, 'zebra', '/x/y.php', 1);
            } finally {
                $second->stop();
            }
        } finally {
            restore_error_handler();
        }

        expect($this->collector->diagnostics())->toBe($second->diagnostics());
        expect(array_column($this->collector->diagnostics(), 'message'))->toBe(['apple', 'zebra']);
    });

    it('carries no key beyond the documented item shape', function () {
        $this->collector->handleError(E_USER_DEPRECATED, 'message', '/x/y.php', 1);

        expect(array_keys(collectorItem($this->collector, 'php_error')))->toBe([
            'type',
            'symbol',
            'message',
            'version',
            'file',
            'line',
            'count',
        ]);
    });
});

describe('DiagnosticsCollector: what it ignores', function () {
    beforeEach(function () {
        // A real file standing in for the WP-CLI Phar, so the archive can be told from the
        // path inside it the way it is in production.
        $this->phar = sys_get_temp_dir() . '/foehn-tests/wp';

        if (!is_dir(dirname($this->phar))) {
            mkdir(dirname($this->phar), 0o777, true);
        }

        file_put_contents($this->phar, 'not really a phar');
    });

    it('keeps a Phar diagnostic in the report but out of the actionable list', function () {
        $this->collector->handleError(
            E_DEPRECATED,
            'Implicitly marking parameter as nullable is deprecated',
            'phar://' . $this->phar . '/php/WP_CLI/Runner.php',
            88,
        );

        expect($this->collector->diagnostics())->toBe([]);
        expect($this->collector->ignored())->toHaveCount(1);
        expect($this->collector->ignored()[0]['file'])->toBe('phar://wp/php/WP_CLI/Runner.php');
    });

    it('does not report the Phar’s own location', function () {
        $this->collector->handleError(E_DEPRECATED, 'message', 'phar://' . $this->phar . '/php/x.php', 1);

        expect($this->collector->ignored()[0]['file'])->not->toContain(dirname($this->phar));
    });

    it('records an error the error_reporting mask excludes', function () {
        // WordPress drops E_DEPRECATED from the mask itself when WP_DEBUG is off, and
        // that is the bit this profile exists to watch — so the mask must not be the
        // thing that decides what is recorded.
        $previous = error_reporting(E_ALL & ~E_USER_DEPRECATED);

        try {
            $this->collector->handleError(E_USER_DEPRECATED, 'excluded from the mask', '/x/y.php', 1);
        } finally {
            error_reporting($previous);
        }

        expect(array_column($this->collector->diagnostics(), 'message'))->toBe(['excluded from the mask']);
        expect($this->spy->calls)->toHaveCount(1);
    });
});

describe('DiagnosticsCollector: in a process of its own', function () {
    it('leaves a real PHP warning to travel its normal path', function () {
        // In a subprocess because it raises a real error: this suite runs with
        // failOnWarning, and the point of the test is that the warning is *not* swallowed.
        $script = <<<'PHP'
            require %s;
            $seen = [];
            set_error_handler(function (int $errno, string $message) use (&$seen): bool {
                $seen[] = $message;

                return false;
            });
            $collector = new Studiometa\Foehn\Verification\Updates\DiagnosticsCollector();
            $collector->start();
            trigger_error('injected by the test', E_USER_WARNING);
            echo 'previous:', implode(',', $seen), "\n";
            echo 'recorded:', count($collector->diagnostics()), "\n";
            PHP;

        $output = [];
        $status = 0;
        exec(
            'php -d display_errors=1 -d error_reporting=-1 -r '
            . escapeshellarg(sprintf($script, var_export(dirname(__DIR__, 2) . '/bootstrap.php', true)))
            . ' 2>&1',
            $output,
            $status,
        );

        $printed = implode("\n", $output);

        expect($status)->toBe(0, $printed);
        expect($printed)->toContain('previous:injected by the test');
        expect($printed)->toContain('recorded:1');

        // PHP's own handling still ran, which is the whole claim: the collector observes
        // and delegates, it does not intercept.
        expect($printed)->toContain('Warning: injected by the test');
    });
});
