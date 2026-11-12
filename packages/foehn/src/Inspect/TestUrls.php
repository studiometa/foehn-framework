<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use Studiometa\Foehn\Discovery\TemplateControllerMap;
use Studiometa\Foehn\Discovery\TemplateNames;
use WP_Post;
use WP_Term;

/**
 * One real URL to check each claimed template with.
 *
 * It reads a few sample requests from WordPress: the front page, the posts page, a
 * search, a missing page, the newest published post of each public post type, the
 * archive of each post type that has one, the author and the month of the newest
 * post, and the term with the most posts of each public taxonomy. Each sample is
 * named as TemplateControllerDiscovery names the request (see TemplateNames), so a
 * template gets the URL of a sample that it renders, not only one whose name it
 * matches: `single` gets no post when `single-*` takes them all.
 *
 * A template no sample renders gets a short reason instead. It reads nothing but
 * links and the newest rows, and writes nothing.
 */
final readonly class TestUrls
{
    /** What the sample search looks for. */
    private const string SEARCH_QUERY = 'test';

    /** A path no content has, so WordPress answers it with its 404 template. */
    private const string MISSING_PATH = '/foehn-not-found/';

    /**
     * The first segment of every template name Føhn gives a request.
     *
     * @var list<string>
     */
    private const array KNOWN_NAMES = [
        '404',
        'search',
        'front',
        'home',
        'single',
        'singular',
        'page',
        'attachment',
        'archive',
        'category',
        'tag',
        'taxonomy',
        'author',
        'date',
        'index',
    ];

    /**
     * @param list<array{url: string, names: non-empty-list<string>}> $requests
     */
    private function __construct(
        private TemplateControllerMap $controllers,
        private array $requests,
    ) {}

    /**
     * Reads the sample requests from WordPress. A WordPress function that does not
     * exist leaves out the samples that need it.
     */
    public static function sample(TemplateControllerMap $controllers): self
    {
        return new self($controllers, self::requests());
    }

    /**
     * The URL of the first sample a claimed pattern renders, or why there is none.
     *
     * @return array{url: string|null, noUrl: string|null}
     */
    public function of(string $pattern): array
    {
        $others = [];

        foreach ($this->requests as $request) {
            $rendered = $this->controllers->pattern($this->controllers->resolve($request['names']));

            if ($rendered === $pattern) {
                return ['url' => $request['url'], 'noUrl' => null];
            }

            if ($rendered !== null && self::named($request['names'], $pattern)) {
                $others[] = $rendered;
            }
        }

        return ['url' => null, 'noUrl' => self::reason($pattern, array_values(array_unique($others)))];
    }

    /**
     * Whether one of the names of a request is the pattern or matches it.
     *
     * @param list<string> $names
     */
    private static function named(array $names, string $pattern): bool
    {
        foreach ($names as $name) {
            if ($name === $pattern || TemplateControllerMap::matches($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $others The patterns that render the samples this one matches
     */
    private static function reason(string $pattern, array $others): string
    {
        if ($others !== []) {
            return sprintf("Its sample URLs render '%s' instead.", implode("', '", $others));
        }

        $first = explode('-', $pattern)[0];

        if (!str_starts_with($pattern, '*') && !in_array($first, self::KNOWN_NAMES, true)) {
            return 'Føhn gives no request a template name like this.';
        }

        return match ($first) {
            'home' => 'The front page lists the posts and renders front-page. Set a posts page in Settings > Reading.',
            'single', 'page', 'author', 'date' => 'No published post renders it.',
            'archive' => $pattern === 'archive'
                ? 'No sample URL renders it.'
                : 'No public post type with an archive renders it.',
            'category', 'tag', 'taxonomy' => 'No term with posts renders it.',
            default => 'No sample URL renders it.',
        };
    }

    /**
     * @return list<array{url: string, names: non-empty-list<string>}>
     */
    private static function requests(): array
    {
        if (!function_exists('home_url') || !function_exists('get_option')) {
            return [];
        }

        $requests = [['url' => home_url('/'), 'names' => ['front-page']]];
        $staticFront = get_option('show_on_front') === 'page';
        $frontPage = $staticFront ? (int) get_option('page_on_front') : 0;
        $postsPage = $staticFront ? (int) get_option('page_for_posts') : 0;

        if ($postsPage > 0 && function_exists('get_permalink')) {
            $url = get_permalink($postsPage);

            if (is_string($url)) {
                $requests[] = ['url' => $url, 'names' => ['home']];
            }
        }

        return [
            ...$requests,
            ['url' => home_url('/?s=' . self::SEARCH_QUERY), 'names' => ['search']],
            ['url' => home_url(self::MISSING_PATH), 'names' => ['404']],
            ...self::posts([$frontPage, $postsPage]),
            ...self::terms(),
        ];
    }

    /**
     * For each public post type, its archive and its newest published post; for the
     * newest post of type `post`, its author and its month.
     *
     * @param list<int> $specialPages The front page and the posts page, which render
     *   front-page and home, not page
     * @return list<array{url: string, names: non-empty-list<string>}>
     */
    private static function posts(array $specialPages): array
    {
        if (!function_exists('get_post_types') || !function_exists('get_posts') || !function_exists('get_permalink')) {
            return [];
        }

        $requests = [];

        foreach (get_post_types(['public' => true]) as $type) {
            // Attachments are not published: they inherit the status of their parent.
            if ($type === 'attachment') {
                continue;
            }

            // The archive of `post` is the posts page.
            if ($type !== 'post' && function_exists('get_post_type_archive_link')) {
                $archive = get_post_type_archive_link($type);

                if (is_string($archive)) {
                    $requests[] = ['url' => $archive, 'names' => ["archive-{$type}"]];
                }
            }

            $posts = get_posts([
                'post_type' => $type,
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post__not_in' => $type === 'page' ? array_values(array_filter($specialPages)) : [],
            ]);
            $post = $posts[0] ?? null;

            if (!$post instanceof WP_Post) {
                continue;
            }

            $requests[] = [
                'url' => get_permalink($post),
                'names' => $type === 'page'
                    ? TemplateNames::page($post->post_name, $post->ID)
                    : TemplateNames::single($type, $post->post_name),
            ];

            if ($type === 'post') {
                $requests = [...$requests, ...self::postArchives($post)];
            }
        }

        return $requests;
    }

    /**
     * The author archive and the month archive of a post.
     *
     * @return list<array{url: string, names: non-empty-list<string>}>
     */
    private static function postArchives(WP_Post $post): array
    {
        $requests = [];

        if (function_exists('get_author_posts_url')) {
            $requests[] = ['url' => get_author_posts_url((int) $post->post_author), 'names' => ['author']];
        }

        if (function_exists('get_month_link')) {
            $requests[] = [
                'url' => get_month_link((int) substr($post->post_date, 0, 4), (int) substr($post->post_date, 5, 2)),
                'names' => ['date'],
            ];
        }

        return $requests;
    }

    /**
     * The term with the most posts of each public taxonomy.
     *
     * @return list<array{url: string, names: non-empty-list<string>}>
     */
    private static function terms(): array
    {
        if (!function_exists('get_taxonomies') || !function_exists('get_terms') || !function_exists('get_term_link')) {
            return [];
        }

        $requests = [];

        foreach (get_taxonomies(['public' => true]) as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'number' => 1,
                'hide_empty' => true,
                'orderby' => 'count',
                'order' => 'DESC',
            ]);
            $term = is_array($terms) ? $terms[0] ?? null : null;

            if (!$term instanceof WP_Term) {
                continue;
            }

            $url = get_term_link($term);

            if (!is_string($url)) {
                continue;
            }

            $requests[] = [
                'url' => $url,
                'names' => match ($taxonomy) {
                    'category' => TemplateNames::category($term->slug),
                    'post_tag' => TemplateNames::tag($term->slug),
                    default => TemplateNames::taxonomy($taxonomy, $term->slug),
                },
            ];
        }

        return $requests;
    }
}
