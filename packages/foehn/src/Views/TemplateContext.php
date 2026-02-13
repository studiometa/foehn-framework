<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views;

use ArrayAccess;
use Studiometa\Foehn\Contracts\Arrayable;
use Timber\Post;
use Timber\PostCollectionInterface;
use Timber\Site;
use Timber\User;

/**
 * Typed context object for template controllers.
 *
 * Provides typed access to Timber globals, safe casting for custom post types,
 * and DTO merging for type-safe custom data.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class TemplateContext implements ArrayAccess
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?Post $post,
        public ?PostCollectionInterface $posts,
        public Site $site,
        public ?User $user,
        private array $extra = [],
    ) {}

    /**
     * Get typed post, optionally cast to a specific class.
     *
     * @template T of Post
     * @param class-string<T> $class
     * @return T|null
     */
    public function post(string $class = Post::class): ?Post
    {
        if ($this->post === null) {
            return null;
        }

        if (!$this->post instanceof $class) {
            return null;
        }

        return $this->post;
    }

    /**
     * Get typed posts collection.
     *
     * When a class is provided, validates that the first post in the collection
     * is an instance of that class (Timber uses the same class for all posts
     * in a collection based on the post type).
     *
     * @template T of Post
     * @param class-string<T>|null $class Expected post class for type validation
     * @return PostCollectionInterface<T>|null
     */
    public function posts(?string $class = null): ?PostCollectionInterface
    {
        if ($this->posts === null) {
            return null;
        }

        if ($class !== null && $this->posts->count() > 0) {
            $first = $this->posts[0];
            if (!$first instanceof $class) {
                return null;
            }
        }

        return $this->posts;
    }

    /**
     * Get a value by key.
     *
     * Checks typed properties first, then dynamic keys.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'post' => $this->post ?? $default,
            'posts' => $this->posts ?? $default,
            'site' => $this->site,
            'user' => $this->user ?? $default,
            default => $this->extra[$key] ?? $default,
        };
    }

    /**
     * Check if a key exists.
     *
     * Returns true for typed properties (even if null) and dynamic keys.
     */
    public function has(string $key): bool
    {
        return match ($key) {
            'post', 'posts', 'site', 'user' => true,
            default => array_key_exists($key, $this->extra),
        };
    }

    /**
     * Add a single key (immutable).
     */
    public function with(string $key, mixed $value): self
    {
        return new self(post: $this->post, posts: $this->posts, site: $this->site, user: $this->user, extra: [
            ...$this->extra,
            $key => $value,
        ]);
    }

    /**
     * Merge an array or Arrayable (immutable).
     *
     * @param array<string, mixed>|Arrayable $data
     */
    public function merge(array|Arrayable $data): self
    {
        $array = $data instanceof Arrayable ? $data->toArray() : $data;

        return new self(post: $this->post, posts: $this->posts, site: $this->site, user: $this->user, extra: [
            ...$this->extra,
            ...$array,
        ]);
    }

    /**
     * Merge a DTO and keep reference for type-safe retrieval.
     *
     * The DTO's properties are flattened into the context for Twig access,
     * and the DTO itself is stored for typed retrieval via dto().
     *
     * @template T of Arrayable
     * @param T $dto
     */
    public function withDto(Arrayable $dto): self
    {
        /** @var array<class-string<Arrayable>, Arrayable> $dtos */
        $dtos = $this->extra['__dtos'] ?? [];
        $dtos[$dto::class] = $dto;

        return new self(post: $this->post, posts: $this->posts, site: $this->site, user: $this->user, extra: [
            ...$this->extra,
            ...$dto->toArray(),
            '__dtos' => $dtos,
        ]);
    }

    /**
     * Get a merged DTO by class (type-safe).
     *
     * @template T of Arrayable
     * @param class-string<T> $class
     * @return T|null
     */
    public function dto(string $class): ?Arrayable
    {
        /** @var T|null */
        return $this->extra['__dtos'][$class] ?? null;
    }

    /**
     * Convert to array for ViewEngine/Twig.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'post' => $this->post,
            'posts' => $this->posts,
            'site' => $this->site,
            'user' => $this->user,
            ...$this->extra,
        ];

        // Remove internal DTO storage if present
        if (isset($array['__dtos'])) {
            unset($array['__dtos']);
        }

        return $array;
    }

    // ──────────────────────────────────────────────
    // ArrayAccess implementation
    // ──────────────────────────────────────────────

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('TemplateContext is immutable, use with() or merge()');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('TemplateContext is immutable');
    }
}
