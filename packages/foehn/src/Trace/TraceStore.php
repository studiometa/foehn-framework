<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Trace;

/**
 * The traces the request tracer writes and `wp foehn trace` reads: one JSON file per
 * request.
 *
 * A file is named `<time>-<id>.json`, so the names sort in the order the requests
 * ended and an id finds its file without reading the others. The store has a cap:
 * after each write, the oldest files beyond `MAX_TRACES` are deleted.
 */
final readonly class TraceStore
{
    public const int MAX_TRACES = 100;

    /** The id the tracer gives a request: 6 lowercase hexadecimal characters. */
    public const string ID_PATTERN = '/^[0-9a-f]{6}$/';

    public function __construct(
        private string $directory,
    ) {}

    /**
     * The store in `wp-content/cache/foehn/traces`, beside the error log.
     */
    public static function inContentDirectory(): self
    {
        $base = defined('WP_CONTENT_DIR') ? constant('WP_CONTENT_DIR') . '/cache' : sys_get_temp_dir();

        return new self($base . '/foehn/traces');
    }

    /**
     * A new id for a request.
     */
    public static function newId(): string
    {
        return bin2hex(random_bytes(3));
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Write the trace of a request, then delete the oldest beyond the cap.
     *
     * @param array<string, mixed> $trace A trace with its `id`
     */
    public function save(array $trace): void
    {
        $id = $trace['id'] ?? null;

        if (!is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1) {
            return;
        }

        $json = json_encode(
            $trace,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        if ($json === false) {
            return;
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            return;
        }

        $time = sprintf('%s%03d', gmdate('YmdHis'), (int) (fmod(microtime(true), 1) * 1000));
        file_put_contents(sprintf('%s/%s-%s.json', $this->directory, $time, $id), $json, LOCK_EX);

        foreach (array_slice(array_reverse($this->files()), self::MAX_TRACES) as $file) {
            unlink($file);
        }
    }

    /**
     * The trace of a request, or null when the store does not have it.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            return null;
        }

        $files = glob(sprintf('%s/*-%s.json', $this->directory, $id)) ?: [];

        return $files === [] ? null : $this->read(end($files));
    }

    /**
     * The trace of the request that ended last, or null when the store is empty.
     *
     * @return array<string, mixed>|null
     */
    public function last(): ?array
    {
        return $this->latest(1)[0] ?? null;
    }

    /**
     * The traces of the requests that ended last, the newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit): array
    {
        $traces = [];

        foreach (array_reverse($this->files()) as $file) {
            if (count($traces) >= $limit) {
                break;
            }

            $trace = $this->read($file);

            if ($trace !== null) {
                $traces[] = $trace;
            }
        }

        return $traces;
    }

    /**
     * Delete every trace.
     *
     * @return int The number of traces deleted
     */
    public function clear(): int
    {
        $deleted = 0;

        foreach ($this->files() as $file) {
            if (!unlink($file)) {
                continue;
            }

            $deleted++;
        }

        return $deleted;
    }

    /**
     * The files of the store, the oldest first.
     *
     * @return list<string>
     */
    private function files(): array
    {
        $files = glob($this->directory . '/*.json') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $file): ?array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        $trace = is_string($contents) ? json_decode($contents, true) : null;

        /** @var array<string, mixed>|null */
        return is_array($trace) ? $trace : null;
    }
}
