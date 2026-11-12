<?php

declare(strict_types=1);

use Studiometa\Foehn\DebugLog\LogParser;
use Studiometa\Foehn\Errors\SourcePaths;

/**
 * @return list<array<string, mixed>>
 */
function parsedLog(string $log, SourcePaths $paths): array
{
    return iterator_to_array(LogParser::entries($log, $paths), false);
}

describe('LogParser', function () {
    beforeEach(function () {
        $this->paths = new SourcePaths('/srv/site/theme');
    });

    it('reads the level, the message, the file and the line of a PHP error', function () {
        $entries = parsedLog(
            "[12-Nov-2026 10:00:00 UTC] PHP Warning:  Undefined variable \$price in /srv/site/theme/app/Controllers/ProductController.php on line 24\n",
            $this->paths,
        );

        expect($entries)->toBe([[
            'time' => '2026-11-12T10:00:00Z',
            'level' => 'warning',
            'message' => 'Undefined variable $price',
            'file' => 'theme/app/Controllers/ProductController.php',
            'line' => 24,
            'trace' => [],
        ]]);
    });

    it('gives each PHP error type its level, and log to a message without one', function (string $line, string $level) {
        expect(parsedLog("[12-Nov-2026 10:00:00 UTC] {$line}", $this->paths)[0]['level'])->toBe($level);
    })->with([
        'fatal error' => ['PHP Fatal error:  Allowed memory size exhausted in /a.php on line 1', 'fatal'],
        'parse error' => ['PHP Parse error:  syntax error in /a.php on line 1', 'fatal'],
        'recoverable' => ['PHP Recoverable fatal error:  Object could not be converted in /a.php on line 1', 'fatal'],
        'notice' => ['PHP Notice:  Function was called incorrectly in /a.php on line 1', 'notice'],
        'deprecated' => ['PHP Deprecated:  Creation of dynamic property in /a.php on line 1', 'deprecated'],
        'error_log()' => ['Cron reschedule event error for hook: foo', 'log'],
        'not a PHP type' => ['PHP is not a level: no', 'log'],
    ]);

    it('attaches the stack trace of an uncaught exception to its entry', function () {
        $log = <<<'LOG'
            [12-Nov-2026 10:00:05 UTC] PHP Fatal error:  Uncaught RuntimeException: Boom in /srv/site/theme/app/Foo.php:12
            Stack trace:
            #0 /srv/site/wp/wp-includes/class-wp-hook.php(324): App\Foo->run()
            #1 /srv/site/theme/functions.php(8): do_action()
            #2 {main}
              thrown in /srv/site/theme/app/Foo.php on line 12
            [12-Nov-2026 10:00:06 UTC] PHP Notice:  Next in /srv/site/theme/app/Bar.php on line 3
            LOG;

        $entries = parsedLog($log, $this->paths);

        expect($entries)->toHaveCount(2);
        expect($entries[0])->toBe([
            'time' => '2026-11-12T10:00:05Z',
            'level' => 'fatal',
            'message' => 'Uncaught RuntimeException: Boom',
            'file' => 'theme/app/Foo.php',
            'line' => 12,
            'trace' => [
                '#0 wp/wp-includes/class-wp-hook.php(324): App\Foo->run()',
                '#1 theme/functions.php(8): do_action()',
                '#2 {main}',
            ],
        ]);
        expect($entries[1]['message'])->toBe('Next');
    });

    it('takes the place from the thrown line when the message does not end with it', function () {
        $log = <<<'LOG'
            [12-Nov-2026 10:00:05 UTC] PHP Fatal error:  Uncaught Exception: First line
            second line
            Stack trace:
            #0 {main}
              thrown in /srv/site/theme/app/Foo.php on line 7
            LOG;

        $entry = parsedLog($log, $this->paths)[0];

        expect($entry['message'])->toBe("Uncaught Exception: First line\nsecond line");
        expect($entry['file'])->toBe('theme/app/Foo.php');
        expect($entry['line'])->toBe(7);
    });

    it('keeps a message of several lines whole', function () {
        $log = "[12-Nov-2026 10:01:00 UTC] Array\n(\n    [a] => 1\n)\n";

        expect(parsedLog($log, $this->paths)[0])->toMatchArray([
            'level' => 'log',
            'message' => "Array\n(\n    [a] => 1\n)",
            'file' => null,
            'line' => null,
        ]);
    });

    it('attaches the frames Xdebug writes with a date of their own', function () {
        $log = <<<'LOG'
            [12-Nov-2026 10:00:00 UTC] PHP Warning:  Boom in /srv/site/theme/a.php on line 2
            [12-Nov-2026 10:00:00 UTC] PHP Stack trace:
            [12-Nov-2026 10:00:00 UTC] PHP   1. {main}() /srv/site/index.php:0
            LOG;

        $entries = parsedLog($log, $this->paths);

        expect($entries)->toHaveCount(1);
        expect($entries[0]['trace'])->toBe(['1. {main}() /srv/site/index.php:0']);
    });

    it('converts the time to UTC, and leaves out the lines before the first date', function () {
        $log = "the end of an entry cut by the read\n[12-Nov-2026 11:00:00 Europe/Paris] Hello\n";

        $entries = parsedLog($log, $this->paths);

        expect($entries)->toHaveCount(1);
        expect($entries[0]['time'])->toBe('2026-11-12T10:00:00Z');
    });

    it('groups the entries that repeat, the group seen last first', function () {
        $log = implode("\n", [
            '[12-Nov-2026 10:00:00 UTC] PHP Warning:  A in /srv/site/theme/a.php on line 1',
            '[12-Nov-2026 10:01:00 UTC] PHP Notice:  B in /srv/site/theme/b.php on line 2',
            '[12-Nov-2026 10:02:00 UTC] PHP Warning:  A in /srv/site/theme/a.php on line 1',
            '[12-Nov-2026 10:03:00 UTC] PHP Warning:  A in /srv/site/theme/a.php on line 9',
        ]);

        $groups = LogParser::group(LogParser::entries($log, $this->paths));

        expect(array_map(static fn(array $group): array => [
            $group['message'],
            $group['line'],
            $group['count'],
            $group['first'],
            $group['last'],
        ], $groups))->toBe([
            ['A', 9, 1, '2026-11-12T10:03:00Z', '2026-11-12T10:03:00Z'],
            ['A', 1, 2, '2026-11-12T10:00:00Z', '2026-11-12T10:02:00Z'],
            ['B', 2, 1, '2026-11-12T10:01:00Z', '2026-11-12T10:01:00Z'],
        ]);
    });
});
