<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Errors;

use ReflectionObject;
use Studiometa\Foehn\Trace\RequestTrace;
use Throwable;
use Twig\Error\Error as TwigError;
use Twig\Template;

/**
 * Builds the JSON entry of one error: what failed, where, and on which request.
 *
 * The stack is short. It gives the frames of the theme's own code, in call order,
 * and then the number of other frames (WordPress, plugins, Composer packages). The
 * place in a Twig template is given as `name:line` when the error comes from a view.
 */
final readonly class EntryBuilder
{
    public const string EXCEPTION = 'exception';

    public const string FATAL = 'fatal';

    public const string WARNING = 'warning';

    public const string NOTICE = 'notice';

    /** @var list<string> */
    public const array LEVELS = [self::EXCEPTION, self::FATAL, self::WARNING, self::NOTICE];

    private const int MAX_OWN_FRAMES = 8;

    private const int MAX_PREVIOUS = 3;

    private const int MAX_MESSAGE = 2000;

    public function __construct(
        private SourcePaths $paths,
        private RequestTrace $request,
    ) {}

    /**
     * The entry of an uncaught exception.
     *
     * @return array<string, mixed>
     */
    public function fromThrowable(Throwable $throwable): array
    {
        $previous = [];

        for (
            $cause = $throwable->getPrevious();
            $cause !== null && count($previous) < self::MAX_PREVIOUS;
            $cause = $cause->getPrevious()
        ) {
            $previous[] = sprintf('%s: %s', $cause::class, self::shorten(strtok($cause->getMessage(), "\n") ?: ''));
        }

        return $this->entry(
            self::EXCEPTION,
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
            $throwable->getTrace(),
            self::twigFromThrowable($throwable),
            $previous,
        );
    }

    /**
     * The entry of a PHP error: a warning or a notice, or a fatal error at shutdown.
     *
     * @param list<array<string, mixed>> $trace The frames above the error, as `debug_backtrace()` gives them
     * @param string|null $twig The place in a Twig template, as `name:line`
     * @return array<string, mixed>
     */
    public function fromError(
        string $level,
        int $type,
        string $message,
        string $file,
        int $line,
        array $trace = [],
        ?string $twig = null,
    ): array {
        return $this->entry($level, self::typeName($type), $message, $file, $line, $trace, $twig, []);
    }

    /**
     * The place in a Twig template where a compiled template raised an error.
     *
     * A compiled template is a PHP class in Twig's cache. When a frame of the trace
     * is that class, its debug information maps the PHP line to the template line.
     *
     * @param list<array<string, mixed>> $trace Frames with their objects
     * @return array{name: string, path: string, line: int}|null
     */
    public static function twigFromTrace(string $file, int $line, array $trace): ?array
    {
        foreach ($trace as $frame) {
            $object = $frame['object'] ?? null;

            if (!$object instanceof Template || new ReflectionObject($object)->getFileName() !== $file) {
                continue;
            }

            $debugInfo = $object->getDebugInfo();
            krsort($debugInfo);
            $source = $object->getSourceContext();

            foreach ($debugInfo as $codeLine => $templateLine) {
                if ($codeLine <= $line) {
                    return ['name' => $source->getName(), 'path' => $source->getPath(), 'line' => $templateLine];
                }
            }

            return ['name' => $source->getName(), 'path' => $source->getPath(), 'line' => 0];
        }

        return null;
    }

    /**
     * The name of a PHP error type, such as `E_WARNING`.
     */
    public static function typeName(int $type): string
    {
        return match ($type) {
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
     * @param list<array<string, mixed>> $trace
     * @param list<string> $previous
     * @return array<string, mixed>
     */
    private function entry(
        string $level,
        string $class,
        string $message,
        string $file,
        int $line,
        array $trace,
        ?string $twig,
        array $previous,
    ): array {
        return [
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'class' => $class,
            'message' => self::shorten($message),
            'file' => $file === '' ? null : $this->paths->relative($file),
            'line' => $line,
            'twig' => $twig,
            'previous' => $previous,
            'stack' => $this->stack($trace),
            ...self::request(),
            ...$this->request->errorFields(),
        ];
    }

    /**
     * The frames of the theme's own code, then how many other frames there are.
     *
     * @param list<array<string, mixed>> $trace
     * @return list<string>
     */
    private function stack(array $trace): array
    {
        $own = [];
        $others = 0;

        foreach ($trace as $frame) {
            $file = is_string($frame['file'] ?? null) ? $frame['file'] : '';

            if ($file === '' || !$this->paths->isOwn($file) || count($own) >= self::MAX_OWN_FRAMES) {
                $others++;

                continue;
            }

            $class = is_string($frame['class'] ?? null) ? $frame['class'] : '';
            $type = is_string($frame['type'] ?? null) ? $frame['type'] : '';
            $function = is_string($frame['function'] ?? null) ? $frame['function'] : '';

            $own[] = sprintf(
                '%s:%d %s%s%s()',
                $this->paths->relative($file),
                is_int($frame['line'] ?? null) ? $frame['line'] : 0,
                $class,
                $type,
                $function,
            );
        }

        if ($others > 0) {
            $own[] = sprintf('+%d other %s', $others, $others === 1 ? 'frame' : 'frames');
        }

        return $own;
    }

    /**
     * The place in a Twig template, from the first Twig error in the chain.
     *
     * Twig wraps an exception thrown while a template renders, and Føhn wraps the
     * Twig error in ViewRenderingFailed, so the Twig error is rarely the outer one.
     */
    private static function twigFromThrowable(Throwable $throwable): ?string
    {
        for ($error = $throwable; $error !== null; $error = $error->getPrevious()) {
            if (!$error instanceof TwigError) {
                continue;
            }

            $name = $error->getSourceContext()?->getName();

            if ($name !== null) {
                return $error->getTemplateLine() > 0 ? $name . ':' . $error->getTemplateLine() : $name;
            }
        }

        return null;
    }

    /**
     * The method and the URL of the request, or `CLI` and the WP-CLI command.
     *
     * @return array{method: string|null, url: string|null}
     */
    private static function request(): array
    {
        if (PHP_SAPI === 'cli') {
            /** @var list<string> $argv */
            $argv = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];

            return ['method' => 'CLI', 'url' => self::shorten(self::redactCommand(array_slice($argv, 1)))];
        }

        return [
            'method' => is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : null,
            'url' => is_string($_SERVER['REQUEST_URI'] ?? null)
                ? self::shorten(self::redactUrl($_SERVER['REQUEST_URI']))
                : null,
        ];
    }

    /**
     * The URL with the value of each query argument removed.
     *
     * The log is a file under wp-content, which the web server can serve: a token, an
     * email or a password in a query string must not go into it. The names stay, so
     * the entry still shows which arguments the request had.
     */
    public static function redactUrl(string $url): string
    {
        $position = strpos($url, '?');

        if ($position === false) {
            return $url;
        }

        $names = [];

        foreach (explode('&', substr($url, $position + 1)) as $pair) {
            if ($pair === '') {
                continue;
            }

            $names[] = explode('=', $pair, 2)[0];
        }

        return substr($url, 0, $position) . ($names === [] ? '' : '?' . implode('&', $names));
    }

    /**
     * The WP-CLI command with the value of each `--option=value` removed.
     *
     * Options such as `--user_pass=` hold secrets. Positional arguments stay, because
     * they name the command and its subject.
     *
     * @param list<string> $arguments The arguments after the `wp` binary.
     */
    public static function redactCommand(array $arguments): string
    {
        $words = ['wp'];

        foreach ($arguments as $argument) {
            $words[] = str_starts_with($argument, '--') && str_contains($argument, '=')
                ? explode('=', $argument, 2)[0] . '=…'
                : $argument;
        }

        return implode(' ', $words);
    }

    private static function shorten(string $text): string
    {
        return mb_strlen($text) > self::MAX_MESSAGE ? mb_substr($text, 0, self::MAX_MESSAGE) . '…' : $text;
    }
}
