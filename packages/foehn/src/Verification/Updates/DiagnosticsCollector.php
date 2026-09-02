<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Verification\Updates;

use Composer\InstalledVersions;
use Phar;

/**
 * Records the PHP and WordPress diagnostics raised inside this one process.
 *
 * A container singleton, started by {@see \Studiometa\Foehn\Kernel} under WP-CLI and
 * read back by {@see RuntimeDiagnosticsCheck}. Four sources: PHP's own error
 * handler, and WordPress's `deprecated_function_run`, `deprecated_hook_run` and
 * `doing_it_wrong_run` hooks.
 *
 * ## What it cannot see
 *
 * The honest limits, because a report that implied otherwise would be worse than no
 * report. It cannot record:
 *
 * - anything raised before the theme starts Føhn — WordPress core loading, mu-plugins,
 *   plugins, and the part of `wp-settings.php` that runs first;
 * - diagnostics from any other process: an HTTP, REST, cron, queue or editor request
 *   raises its own, and nothing here ever sees them;
 * - a fatal error that stops the theme or the command from loading at all, which ends
 *   the process before there is a report to write.
 *
 * So a clean report means "this WP-CLI process raised nothing actionable", not "the
 * site is compatible". PHP-FPM and WordPress logs stay part of an update review.
 *
 * ## Collection never changes behaviour
 *
 * The error handler records and then delegates to whatever handler was installed
 * before it, returning that handler's verdict; with no previous handler it returns
 * `false` so PHP's normal handling runs. Nothing here suppresses, converts or
 * re-raises an error.
 */
final class DiagnosticsCollector
{
    /**
     * Diagnostics raised inside a Phar are recorded and then excluded from the exit
     * status. WP-CLI ships as a Phar and its own vendored code raises deprecations on
     * new PHP versions — findings a project cannot act on, and the only default ignore
     * rule that ships. No project allowlist: there is no evidence yet about which
     * fingerprints would need one.
     */
    private const PHAR_SCHEME = 'phar://';

    private bool $started = false;

    /** @var array<string, array<string, mixed>> */
    private array $items = [];

    /** @var list<string>|null Resolved once: the constants it reads cannot change in a process. */
    private ?array $bases = null;

    /** @var (callable(int, string, string, int): bool)|null */
    private $previousHandler = null;

    /**
     * Begin recording. Calling it again does nothing.
     *
     * Idempotent because a second call would stack a second error handler on top of the
     * first, and every error would then be recorded twice.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;

        /** @var (callable(int, string, string, int): bool)|null $previous */
        $previous = set_error_handler($this->handleError(...));
        $this->previousHandler = $previous;

        add_action('deprecated_function_run', $this->recordDeprecatedFunction(...), 10, 3);
        add_action('deprecated_hook_run', $this->recordDeprecatedHook(...), 10, 4);
        add_action('doing_it_wrong_run', $this->recordDoingItWrong(...), 10, 3);
    }

    /**
     * Stop recording and put the previous error handler back.
     *
     * Production never calls this — the process ends instead. It exists so that a test
     * can leave the handler stack exactly as it found it, which a suite running with
     * `failOnWarning` requires.
     */
    public function stop(): void
    {
        if (!$this->started) {
            return;
        }

        restore_error_handler();

        $this->started = false;
        $this->previousHandler = null;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * PHP's error handler: record, then let normal handling proceed.
     *
     * Everything PHP hands over is recorded, including errors the current
     * `error_reporting()` mask excludes and expressions somebody wrote `@` in front of.
     * PHP calls a user handler regardless of the mask, and filtering on it here would
     * make the gate silent on exactly the site that needs it: WordPress narrows
     * `error_reporting()` itself when `WP_DEBUG` is off, and one of the bits it drops is
     * `E_DEPRECATED` — the diagnostic this profile exists to catch.
     *
     * The cost is that a suppressed warning is reported too. That is the intended trade:
     * a finding somebody has to read and dismiss beats a clean report that was never
     * looking.
     *
     * @return bool Whatever the previous handler decided, or false so PHP handles it
     */
    public function handleError(int $errno, string $message, string $file = '', int $line = 0): bool
    {
        $this->record('php_error', self::level($errno), $message, '', $file, $line);

        if ($this->previousHandler !== null) {
            // Its verdict, not ours: a previous handler that returned true asked PHP to
            // stop handling the error, and passing that through is what keeps this
            // collector invisible to the code around it.
            return ($this->previousHandler)($errno, $message, $file, $line) === true;
        }

        return false;
    }

    /**
     * `deprecated_function_run`: a function WordPress or a plugin has retired.
     */
    public function recordDeprecatedFunction(string $function, string $replacement = '', string $version = ''): void
    {
        [$file, $line] = $this->source();

        $this->record(
            'deprecated_function',
            $function,
            $this->replacementMessage($replacement),
            $version,
            $file,
            $line,
        );
    }

    /**
     * `deprecated_hook_run`: an action or filter that still fires but should not be used.
     */
    public function recordDeprecatedHook(
        string $hook,
        string $replacement = '',
        string $version = '',
        string $message = '',
    ): void {
        [$file, $line] = $this->source();

        $this->record(
            'deprecated_hook',
            $hook,
            trim($this->replacementMessage($replacement) . ' ' . $message),
            $version,
            $file,
            $line,
        );
    }

    /**
     * `doing_it_wrong_run`: WordPress used in a way that is not supported.
     */
    public function recordDoingItWrong(string $function, string $message = '', string $version = ''): void
    {
        [$file, $line] = $this->source();

        $this->record('doing_it_wrong', $function, $message, $version, $file, $line);
    }

    /**
     * The actionable findings, in a stable order.
     *
     * @return list<array<string, mixed>>
     */
    public function diagnostics(): array
    {
        return $this->collect(false);
    }

    /**
     * The findings kept for the record but excluded from the exit status.
     *
     * @return list<array<string, mixed>>
     */
    public function ignored(): array
    {
        return $this->collect(true);
    }

    /**
     * A human-readable name for a PHP error level.
     *
     * The level is the closest thing a PHP error has to a symbol, and it is what tells
     * a deprecation apart from a warning when the message alone does not.
     */
    private static function level(int $errno): string
    {
        return match ($errno) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_UNKNOWN',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collect(bool $ignored): array
    {
        $items = [];

        foreach ($this->items as $item) {
            if ($item['ignored'] !== $ignored) {
                continue;
            }

            // The flag is a classification, not a finding: `ignored` is a report section,
            // so repeating it inside each item would be one more thing that could disagree
            // with the section it is in.
            unset($item['ignored']);

            $items[] = $item;
        }

        // Sorted on read rather than on write, over the fields the identity is built from:
        // two runs that raised the same diagnostics in a different order have to produce
        // the same report, or a CI artifact diff says nothing.
        usort(
            $items,
            static fn(array $a, array $b): int => (
                [$a['type'], $a['symbol'], $a['message'], $a['file'], $a['line']] <=> [
                    $b['type'],
                    $b['symbol'],
                    $b['message'],
                    $b['file'],
                    $b['line'],
                ]
            ),
        );

        return $items;
    }

    /**
     * Add one finding, or count a repeat of one already recorded.
     */
    private function record(
        string $type,
        string $symbol,
        string $message,
        string $version,
        string $file,
        int $line,
    ): void {
        $key = implode('|', [$type, $symbol, $message, $file, $line]);

        if (($this->items[$key] ?? null) !== null) {
            $this->items[$key]['count']++;

            return;
        }

        $this->items[$key] = [
            'type' => $type,
            'symbol' => $symbol,
            'message' => $message,
            'version' => $version,
            'file' => $this->normalize($file),
            'line' => $line,
            'count' => 1,
            'ignored' => str_starts_with($file, self::PHAR_SCHEME),
        ];
    }

    /**
     * Where the code that raised a hook-based diagnostic lives.
     *
     * The three WordPress hooks carry no file or line — `_deprecated_function()` is
     * called from inside the function being retired, and the caller is what a project
     * has to change. So the first frame that is neither WordPress core nor this
     * collector is the answer, and it is also how a diagnostic raised inside the WP-CLI
     * Phar is spotted: that frame's file carries the `phar://` scheme.
     *
     * @return array{0: string, 1: int}
     */
    private function source(): array
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50) as $frame) {
            $file = $frame['file'] ?? null;

            if (!is_string($file) || $file === __FILE__ || $this->isCore($file)) {
                continue;
            }

            return [$file, (int) ($frame['line'] ?? 0)];
        }

        return ['', 0];
    }

    /**
     * Whether a file belongs to WordPress core.
     *
     * `wp-content` is excluded rather than `wp-includes` and `wp-admin` listed, because
     * that one rule is right in both layouts: a classic install keeps `wp-content`
     * inside `ABSPATH`, and a Bedrock-style one keeps it beside it.
     */
    private function isCore(string $file): bool
    {
        if (!defined('ABSPATH') || !str_starts_with($file, (string) constant('ABSPATH'))) {
            return false;
        }

        return !defined('WP_CONTENT_DIR') || !str_starts_with($file, (string) constant('WP_CONTENT_DIR'));
    }

    /**
     * A path the report can carry: relative to the install, or a bare file name.
     *
     * No absolute path ever reaches the report. An absolute path names the machine that
     * ran the command — a CI runner's checkout directory, a container's mount point —
     * so two runs of the same site would produce different bytes, and a report from a
     * developer's laptop would carry their home directory into a shared artifact.
     */
    private function normalize(string $file): string
    {
        if ($file === '') {
            return '';
        }

        if (str_starts_with($file, self::PHAR_SCHEME)) {
            return $this->normalizePhar($file);
        }

        foreach ($this->bases() as $base) {
            if (str_starts_with($file, $base)) {
                return substr($file, strlen($base));
            }
        }

        // Nothing to be relative to — a PHP include path, a stream, a file above the
        // install. The name alone is less useful than a path, and it is the most that
        // can be said without disclosing the filesystem it sits on.
        return basename($file);
    }

    /**
     * A path inside a Phar, with the archive named but not located.
     *
     * `phar:///usr/local/bin/wp/php/WP_CLI/Runner.php` becomes
     * `phar://wp/php/WP_CLI/Runner.php`: which archive it was is the useful half, and
     * where that archive is installed is the half that would pin the report to one
     * machine.
     */
    private function normalizePhar(string $file): string
    {
        $inside = substr($file, strlen(self::PHAR_SCHEME));
        $archive = Phar::running(false);

        if ($archive === '' || !str_starts_with($inside, $archive)) {
            $archive = self::findArchive($inside);
        }

        if ($archive !== '') {
            return self::PHAR_SCHEME . basename($archive) . substr($inside, strlen($archive));
        }

        // The boundary between the archive and the file inside it could not be found, so
        // the file name is all that can be reported without the path that located it.
        return self::PHAR_SCHEME . basename($inside);
    }

    /**
     * Where the archive ends and the file inside it begins.
     *
     * `Phar::running()` answers this for the archive that is executing, which is the
     * WP-CLI case and therefore the one that matters. This covers the rest: a Phar is a
     * real file, so the longest prefix of the path that is a file on disk is the
     * archive.
     */
    private static function findArchive(string $inside): string
    {
        $offset = 0;

        while (($offset = strpos($inside, '/', $offset + 1)) !== false) {
            $candidate = substr($inside, 0, $offset);

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * The directories a source path can be expressed relative to, longest first.
     *
     * `ABSPATH` covers core. The directory above `wp-content` covers themes, plugins and
     * uploads in either layout — and in a Bedrock-style install it is also the web root.
     * The Composer root covers everything a project installed, which is where a
     * deprecation from a dependency will be.
     *
     * @return list<string>
     */
    private function bases(): array
    {
        if ($this->bases !== null) {
            return $this->bases;
        }

        $candidates = [];

        if (defined('ABSPATH')) {
            $candidates[] = (string) constant('ABSPATH');
        }

        if (defined('WP_CONTENT_DIR')) {
            $candidates[] = dirname((string) constant('WP_CONTENT_DIR'));
        }

        $candidates[] = self::composerRoot();

        $bases = [];

        foreach ($candidates as $candidate) {
            $base = rtrim($candidate, '/');

            // A single-segment base would relativize half the filesystem: `/var/log/x.php`
            // read as `log/x.php` is a path that looks like it belongs to the project.
            if ($base === '' || substr_count($base, '/') < 2) {
                continue;
            }

            $bases[] = $base . '/';
        }

        $bases = array_values(array_unique($bases));

        usort($bases, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $this->bases = $bases;
    }

    /**
     * The root of the Composer project, or an empty string outside one.
     */
    private static function composerRoot(): string
    {
        if (!class_exists(InstalledVersions::class)) {
            return '';
        }

        return (string) realpath(InstalledVersions::getRootPackage()['install_path']);
    }

    /**
     * WordPress's own phrasing for a replacement, or the absence of one.
     */
    private function replacementMessage(string $replacement): string
    {
        return $replacement === '' ? 'No alternative is available.' : "Use {$replacement} instead.";
    }
}
