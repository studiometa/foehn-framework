<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Timber;

use Studiometa\Foehn\Contracts\TimberRepositoryInterface;
use Timber\Menu;
use Timber\Post;
use Timber\PostQuery;
use Timber\Term;
use Timber\Timber;
use Timber\User;

/**
 * Repository for Timber content retrieval.
 *
 * Wraps Timber's static methods with sensible defaults and conventions.
 */
final readonly class TimberRepository implements TimberRepositoryInterface
{
    /**
     * Get a single post by ID.
     *
     * Returns null for non-published posts by default.
     */
    public function post(int $id, bool $publishedOnly = true): ?Post
    {
        $post = Timber::get_post($id);

        if (!$post instanceof Post) {
            return null;
        }

        if ($publishedOnly && $post->post_status !== 'publish') {
            return null;
        }

        return $post;
    }

    /**
     * Get the current post from the query.
     */
    public function currentPost(): ?Post
    {
        $post = Timber::get_post();

        return $post instanceof Post ? $post : null;
    }

    /**
     * Get posts with query arguments.
     *
     * @param array<string, mixed> $args WP_Query arguments
     */
    public function posts(array $args = []): ?PostQuery
    {
        $posts = Timber::get_posts($args);

        return $posts instanceof PostQuery ? $posts : null;
    }

    /**
     * Get a term by ID.
     */
    public function term(int $id, string $taxonomy = 'category'): ?Term
    {
        $term = Timber::get_term_by('id', $id, $taxonomy);

        return $term instanceof Term ? $term : null;
    }

    /**
     * Get a term by slug.
     */
    public function termBySlug(string $slug, string $taxonomy = 'category'): ?Term
    {
        $term = Timber::get_term_by('slug', $slug, $taxonomy);

        return $term instanceof Term ? $term : null;
    }

    /**
     * Get terms with query arguments.
     *
     * @param string|array<string, mixed> $args Taxonomy name or query arguments
     * @return iterable<Term>
     */
    public function terms(string|array $args = []): iterable
    {
        /** @var iterable<Term> */
        return Timber::get_terms($args);
    }

    /**
     * Get a menu by location.
     */
    public function menu(string $location): ?Menu
    {
        $menu = Timber::get_menu_by('location', $location);

        return $menu instanceof Menu ? $menu : null;
    }

    /**
     * Get a menu by slug.
     */
    public function menuBySlug(string $slug): ?Menu
    {
        $menu = Timber::get_menu($slug);

        return $menu instanceof Menu ? $menu : null;
    }

    /**
     * Get the current user.
     */
    public function currentUser(): ?User
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user = Timber::get_user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Get a user by ID.
     */
    public function user(int $id): ?User
    {
        $user = Timber::get_user($id);

        return $user instanceof User ? $user : null;
    }

    /**
     * Get Timber's global context.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return Timber::context();
    }

    /**
     * Get Timber's global context (cached values only).
     *
     * @return array<string, mixed>
     */
    public function globalContext(): array
    {
        return Timber::context_global();
    }
}
