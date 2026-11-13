<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Smoke\Support;

/**
 * One HTTP response, reduced to what a page-cache assertion needs.
 */
final readonly class Response
{
    /**
     * The comment the recorder appends to a page it stores.
     *
     * Its absence is what proves a response was rendered rather than served: two renders
     * of a page that carries no marker are byte-identical, so comparing bodies cannot
     * tell them apart.
     */
    public const MARKER = '<!-- foehn cache:';

    /**
     * @param array<string, string> $headers Lowercased names, last value wins
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * HIT, MISS or BYPASS — or null when the site is not emitting debug headers.
     */
    public function cache(): ?string
    {
        return $this->header('x-foehn-cache');
    }

    /**
     * Which of the four readers answered.
     */
    public function via(): ?string
    {
        return $this->header('x-foehn-cache-via');
    }

    public function reason(): ?string
    {
        return $this->header('x-foehn-cache-reason');
    }

    /**
     * A description for a failure message, so a red test says what actually happened.
     */
    public function describe(): string
    {
        return sprintf(
            'HTTP %d, X-Foehn-Cache: %s, via: %s, reason: %s',
            $this->status,
            $this->cache() ?? '(none)',
            $this->via() ?? '(none)',
            $this->reason() ?? '(none)',
        );
    }
}
