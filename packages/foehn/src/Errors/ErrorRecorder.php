<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Errors;

use Throwable;

/**
 * Writes the errors of a request to the error log, with what the request rendered.
 *
 * It records:
 *
 * - each uncaught exception;
 * - each fatal error, read with `error_get_last()` at shutdown;
 * - each warning and notice raised in the theme's own code, or in a Twig template of
 *   the theme. A warning of WordPress, a plugin or a Composer package is not recorded.
 *   A warning silenced with `@` is not recorded. Deprecations are not recorded.
 *
 * ## It changes nothing in how PHP and WordPress handle the error
 *
 * The error handler records, and then gives the error to the handler that was there
 * before, or to PHP. The exception handler records, and then gives the exception to
 * the handler that was there before; with none, it throws the exception again, so PHP
 * ends the request with its own fatal error. WordPress's fatal error handler
 * (`WP_Fatal_Error_Handler`) then runs as usual: it reads the same `error_get_last()`
 * in its own shutdown function, which WordPress registered before the theme loaded.
 *
 * ## Limits
 *
 * It starts when the theme boots Føhn: an error raised before, in WordPress core or a
 * plugin, is not recorded. An exception that code catches is not recorded. One request
 * writes each error once, and writes at most `MAX_PER_REQUEST` entries.
 */
final class ErrorRecorder
{
    public const int MAX_PER_REQUEST = 20;

    private const int FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private bool $started = false;

    /** Set while an entry is written, so that an error in the recorder is not recorded again. */
    private bool $recording = false;

    /** Set when an uncaught exception was recorded, so that its fatal error at shutdown is not. */
    private bool $exceptionRecorded = false;

    /** @var array<string, true> */
    private array $written = [];

    /** @var (callable(int, string, string, int): bool)|null */
    private $previousErrorHandler = null;

    /** @var (callable(Throwable): void)|null */
    private $previousExceptionHandler = null;

    public function __construct(
        private readonly ErrorLog $log,
        private readonly EntryBuilder $entries,
        private readonly SourcePaths $paths,
        private readonly RequestContext $request,
    ) {}

    /**
     * Begin recording. Calling it again does nothing.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;

        /** @var (callable(int, string, string, int): bool)|null $previousErrorHandler */
        $previousErrorHandler = set_error_handler($this->handleError(...));
        $this->previousErrorHandler = $previousErrorHandler;

        /** @var (callable(Throwable): void)|null $previousExceptionHandler */
        $previousExceptionHandler = set_exception_handler($this->handleException(...));
        $this->previousExceptionHandler = $previousExceptionHandler;

        register_shutdown_function($this->shutdown(...));

        add_filter('timber/loader/render_data', $this->onRenderData(...), PHP_INT_MAX, 2);
    }

    /**
     * Stop recording and put the previous handlers back.
     *
     * The shutdown function cannot be removed: it does nothing once this has run.
     */
    public function stop(): void
    {
        if (!$this->started) {
            return;
        }

        restore_error_handler();
        restore_exception_handler();

        $this->started = false;
        $this->previousErrorHandler = null;
        $this->previousExceptionHandler = null;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * PHP's error handler: record a warning or a notice of the theme, then let the
     * previous handler, or PHP, handle it.
     *
     * @return bool Whatever the previous handler decided, or false so PHP handles it
     */
    public function handleError(int $type, string $message, string $file = '', int $line = 0): bool
    {
        $level = match ($type) {
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => EntryBuilder::WARNING,
            E_NOTICE, E_USER_NOTICE => EntryBuilder::NOTICE,
            default => null,
        };

        // `error_reporting()` leaves out the error when `@` silenced it.
        if ($level !== null && (error_reporting() & $type) !== 0 && !$this->recording) {
            $this->recordError($level, $type, $message, $file, $line);
        }

        if ($this->previousErrorHandler !== null) {
            return ($this->previousErrorHandler)($type, $message, $file, $line) === true;
        }

        return false;
    }

    /**
     * PHP's exception handler: record the exception, then let the previous handler
     * handle it, or throw it again so that PHP ends the request with its fatal error.
     *
     * @throws Throwable The exception, when no handler was there before
     */
    public function handleException(Throwable $throwable): void
    {
        if ($this->started && !$this->recording) {
            $this->exceptionRecorded = true;
            $this->write($this->entries->fromThrowable($throwable));
        }

        if ($this->previousExceptionHandler !== null) {
            ($this->previousExceptionHandler)($throwable);

            return;
        }

        throw $throwable;
    }

    /**
     * The shutdown function.
     */
    public function shutdown(): void
    {
        $this->handleShutdown(error_get_last());
    }

    /**
     * Record the fatal error that ended the request, if any.
     *
     * @param array{type: int, message: string, file: string, line: int}|null $error What `error_get_last()` gives
     */
    public function handleShutdown(?array $error): void
    {
        if (!$this->started || $error === null || ($error['type'] & self::FATAL) === 0) {
            return;
        }

        // The exception handler threw it again: it is recorded already, with its class.
        if ($this->exceptionRecorded && str_starts_with($error['message'], 'Uncaught ')) {
            return;
        }

        // PHP adds the stack trace of an uncaught exception to the message.
        $message = explode("\nStack trace:", $error['message'], 2)[0];

        $this->write($this->entries->fromError(
            EntryBuilder::FATAL,
            $error['type'],
            $message,
            $error['file'],
            $error['line'],
        ));
    }

    /**
     * `timber/loader/render_data`: remember the view Timber renders and its context keys.
     *
     * The data goes back unchanged.
     */
    public function onRenderData(mixed $data, mixed $file = ''): mixed
    {
        if (is_array($data) && is_string($file)) {
            $this->request->renderView($file, array_map(strval(...), array_keys($data)));
        }

        return $data;
    }

    private function recordError(string $level, int $type, string $message, string $file, int $line): void
    {
        // Skip this method and handleError(): the frames above them are the code that raised it.
        $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 64), 2);
        $twig = EntryBuilder::twigFromTrace($file, $line, $trace);

        $own = $twig !== null ? $this->paths->isOwn($twig['path']) : $this->paths->isOwn($file);

        if (!$own) {
            return;
        }

        $this->write($this->entries->fromError(
            $level,
            $type,
            $message,
            $file,
            $line,
            $trace,
            $twig === null ? null : $twig['name'] . ':' . $twig['line'],
        ));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function write(array $entry): void
    {
        $key = implode('|', [$entry['level'], $entry['class'], $entry['file'], $entry['line'], $entry['message']]);

        if (($this->written[$key] ?? false) || count($this->written) >= self::MAX_PER_REQUEST) {
            return;
        }

        $this->written[$key] = true;
        $this->recording = true;

        try {
            $this->log->append($entry);
        } finally {
            $this->recording = false;
        }
    }
}
