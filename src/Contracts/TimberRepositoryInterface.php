<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use Timber\Menu;
use Timber\Post;
use Timber\PostQuery;
use Timber\Term;
use Timber\User;

/**
 * Interface for Timber content retrieval.
 */
interface TimberRepositoryInterface
{
    /**
     * Get a single post by ID.
     */
    public function post(int $id, bool $publishedOnly = true): ?Post;

    /**
     * Get the current post from the query.
     */
    public function currentPost(): ?Post;

    /**
     * Get posts with query arguments.
     *
     * @param array<string, mixed> $args WP_Query arguments
     */
    public function posts(array $args = []): ?PostQuery;

    /**
     * Get a term by ID.
     */
    public function term(int $id, string $taxonomy = 'category'): ?Term;

    /**
     * Get a term by slug.
     */
    public function termBySlug(string $slug, string $taxonomy = 'category'): ?Term;

    /**
     * Get terms with query arguments.
     *
     * @param string|array<string, mixed> $args Taxonomy name or query arguments
     * @return iterable<Term>
     */
    public function terms(string|array $args = []): iterable;

    /**
     * Get a menu by location.
     */
    public function menu(string $location): ?Menu;

    /**
     * Get a menu by slug.
     */
    public function menuBySlug(string $slug): ?Menu;

    /**
     * Get the current user.
     */
    public function currentUser(): ?User;

    /**
     * Get a user by ID.
     */
    public function user(int $id): ?User;

    /**
     * Get Timber's global context.
     *
     * @return array<string, mixed>
     */
    public function context(): array;

    /**
     * Get Timber's global context (cached values only).
     *
     * @return array<string, mixed>
     */
    public function globalContext(): array;
}
