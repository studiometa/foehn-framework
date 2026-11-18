<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search;

use Studiometa\Foehn\Search\Contracts\SearchDocumentInterface;
use Tempest\Container\Singleton;

/**
 * The searchable post types, each with the class that maps its posts.
 *
 * Filled by {@see \Studiometa\Foehn\Search\Discovery\SearchDocumentDiscovery}.
 */
#[Singleton]
final class SearchDocuments
{
    /** @var array<string, class-string<SearchDocumentInterface>> */
    private array $classes = [];

    /**
     * @param class-string<SearchDocumentInterface> $className
     */
    public function register(string $postType, string $className): void
    {
        $this->classes[$postType] = $className;
    }

    /**
     * @return list<string>
     */
    public function postTypes(): array
    {
        return array_keys($this->classes);
    }

    public function has(string $postType): bool
    {
        return array_key_exists($postType, $this->classes);
    }

    /**
     * @return class-string<SearchDocumentInterface>|null
     */
    public function classFor(string $postType): ?string
    {
        return $this->classes[$postType] ?? null;
    }
}
