<?php

declare(strict_types=1);

use Studiometa\Foehn\Console\WpCli;
use Studiometa\Foehn\DebugLog\DebugLog;
use Studiometa\Foehn\DebugLog\LogCommand;
use Studiometa\Foehn\Errors\SourcePaths;

/**
 * What the command printed with log(), in order.
 *
 * @return list<string>
 */
function debugLogOutput(): array
{
    return array_column(array_column(wp_stub_get_calls('wp_cli_log'), 'args'), 'message');
}

/**
 * The groups the last call with --format=json printed.
 *
 * @return list<array<string, mixed>>
 */
function debugLogJson(): array
{
    $lines = array_column(array_column(wp_stub_get_calls('wp_cli_line'), 'args'), 'message');

    return json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
}

describe('wp foehn log', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->file = (string) tempnam(sys_get_temp_dir(), 'foehn-debug-log-');
        file_put_contents($this->file, implode("\n", [
            '[12-Nov-2026 08:00:00 UTC] PHP Deprecated:  Old call in /srv/site/theme/app/Old.php on line 5',
            '[12-Nov-2026 10:00:00 UTC] PHP Warning:  Undefined variable $price in /srv/site/theme/app/Controllers/ProductController.php on line 24',
            '[12-Nov-2026 10:00:05 UTC] PHP Fatal error:  Uncaught RuntimeException: Boom in /srv/site/theme/app/Foo.php:12',
            'Stack trace:',
            '#0 /srv/site/theme/app/Bar.php(3): App\Foo->run()',
            '#1 /srv/site/theme/app/Baz.php(4): App\Bar->run()',
            '#2 /srv/site/theme/app/Qux.php(5): App\Baz->run()',
            '#3 /srv/site/theme/functions.php(6): App\Qux->run()',
            '#4 {main}',
            '  thrown in /srv/site/theme/app/Foo.php on line 12',
            '[12-Nov-2026 10:05:00 UTC] PHP Warning:  Undefined variable $price in /srv/site/theme/app/Controllers/ProductController.php on line 24',
            '',
        ]));
        $this->paths = new SourcePaths('/srv/site/theme');
        $this->command = new LogCommand(new WpCli(), new DebugLog($this->file, true), $this->paths);
    });

    afterEach(fn() => is_file($this->file) && unlink($this->file));

    it('prints the groups, the group seen last first', function () {
        ($this->command)([], []);

        expect(debugLogOutput())->toBe([
            'Undefined variable $price',
            '  warning ×2 at 2026-11-12 10:05:00 UTC in theme/app/Controllers/ProductController.php:24, first at 2026-11-12 10:00:00 UTC',
            'Uncaught RuntimeException: Boom',
            '  fatal at 2026-11-12 10:00:05 UTC in theme/app/Foo.php:12',
            '    #0 theme/app/Bar.php(3): App\Foo->run()',
            '    #1 theme/app/Baz.php(4): App\Bar->run()',
            '    #2 theme/app/Qux.php(5): App\Baz->run()',
            '    +2 more lines',
            'Old call',
            '  deprecated at 2026-11-12 08:00:00 UTC in theme/app/Old.php:5',
        ]);
    });

    it('prints every field as JSON on one line', function () {
        ($this->command)([], ['format' => 'json', 'limit' => '1']);

        expect(debugLogJson())->toBe([[
            'level' => 'warning',
            'message' => 'Undefined variable $price',
            'file' => 'theme/app/Controllers/ProductController.php',
            'line' => 24,
            'count' => 2,
            'first' => '2026-11-12T10:00:00Z',
            'last' => '2026-11-12T10:05:00Z',
            'trace' => [],
        ]]);
    });

    it('says how to see the groups the limit leaves out', function () {
        ($this->command)([], ['limit' => '1']);

        $output = debugLogOutput();

        expect(end($output))->toBe('2 more: wp foehn log --limit=3. Every field: --format=json.');
    });

    it('filters by level, date and text', function (array $options, array $messages) {
        ($this->command)([], ['format' => 'json', ...$options]);

        expect(array_column(debugLogJson(), 'message'))->toBe($messages);
    })->with([
        'level' => [['level' => 'fatal,deprecated'], ['Uncaught RuntimeException: Boom', 'Old call']],
        'since' => [['since' => '2026-11-12 09:00'], ['Undefined variable $price', 'Uncaught RuntimeException: Boom']],
        'grep in the message' => [['grep' => 'PRICE'], ['Undefined variable $price']],
        'grep in the trace' => [['grep' => 'Qux'], ['Uncaught RuntimeException: Boom']],
        'nothing' => [['grep' => 'nothing like this'], []],
    ]);

    it('counts only the entries the filters keep', function () {
        ($this->command)([], ['format' => 'json', 'since' => '2026-11-12 10:01']);

        expect(debugLogJson()[0])->toMatchArray(['count' => 1, 'first' => '2026-11-12T10:05:00Z']);
    });

    it('refuses an unknown level, format, limit, date or empty text', function (array $options, string $message) {
        ($this->command)([], $options);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])->toBe($message);
    })->with([
        'level' => [['level' => 'error'], "Unknown level 'error'. Use fatal, warning, notice, deprecated, log."],
        'format' => [['format' => 'yaml'], 'Unknown format. Use text or json.'],
        'limit' => [['limit' => '0'], '--limit must be a number of 1 or more.'],
        'date' => [['since' => 'not a date'], "'not a date' is not a date."],
        'grep' => [['grep' => true], '--grep must be the text to find, such as --grep=ProductController.'],
    ]);

    it('says how to turn the log on when WP_DEBUG_LOG is off', function () {
        (new LogCommand(new WpCli(), new DebugLog(null, false), $this->paths))([], []);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])
            ->toStartWith('The debug log is off: WP_DEBUG_LOG is not set. Set WP_DEBUG=true in .env');
    });

    it('says when the file does not exist yet', function () {
        (new LogCommand(new WpCli(), new DebugLog('/srv/site/wp-content/debug.log', true), $this->paths))([], []);

        expect(debugLogOutput())->toBe(['No debug log at wp-content/debug.log: nothing was written to it yet.']);
    });

    it('prints an empty list as JSON when the file does not exist yet', function () {
        (new LogCommand(new WpCli(), new DebugLog('/srv/site/wp-content/debug.log', true), $this->paths))([], [
            'format' => 'json',
        ]);

        expect(debugLogJson())->toBe([]);
        expect(wp_stub_get_calls('wp_cli_warning'))->toBe([]);
    });

    it('says when it cannot read the file', function () {
        chmod($this->file, 0o000);

        if (is_readable($this->file)) {
            $this->markTestSkipped('This user reads any file, such as root.');
        }

        ($this->command)([], []);

        expect(wp_stub_get_calls('wp_cli_error')[0]['args']['message'])
            ->toBe(sprintf('Cannot read %s: the user that runs WP-CLI needs read access to it.', $this->file));
    });

    it('says when WP_DEBUG is off and WordPress writes nothing new', function () {
        (new LogCommand(new WpCli(), new DebugLog($this->file, false), $this->paths))([], ['limit' => '1']);

        $output = debugLogOutput();

        expect(end($output))
            ->toBe(
                'WP_DEBUG is off, so WordPress writes nothing new to this log. Set WP_DEBUG=true in .env, or define WP_DEBUG as true in wp-config.php.',
            );

        (new LogCommand(new WpCli(), new DebugLog($this->file, false), $this->paths))([], ['format' => 'json']);

        expect(wp_stub_get_calls('wp_cli_warning'))->toHaveCount(1);
    });

    it('says when it read only the end of a large log', function () {
        $handle = fopen($this->file, 'ab');
        $line = '[12-Nov-2026 11:00:00 UTC] PHP Notice:  Filler in /srv/site/theme/a.php on line 1' . "\n";
        fwrite($handle, str_repeat($line, (int) (DebugLog::MAX_BYTES / strlen($line)) + 10));
        fclose($handle);

        ($this->command)([], []);

        expect(debugLogOutput())->toContain('Read the last 8 MB of the 9 MB log: older entries are not counted.');
        expect(debugLogOutput()[0])->toBe('Filler');
    });
});
