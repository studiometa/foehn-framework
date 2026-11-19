<?php

declare(strict_types=1);

use Demo\Seeders\JournalSeeder;
use Demo\Seeders\LanguageSeeder;
use Demo\Seeders\MediaSeeder;
use Demo\Seeders\MenuSeeder;
use Demo\Seeders\PageSeeder;
use Demo\Seeders\Photographs;
use Demo\Seeders\PressSeeder;
use Demo\Seeders\ProjectSeeder;
use Demo\Seeders\SettingsSeeder;
use Demo\Seeders\TestimonialSeeder;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

// PageSeeder reads the registered pattern; the WordPress stubs do not have the registry.
if (!class_exists(WP_Block_Patterns_Registry::class)) {
    final class WP_Block_Patterns_Registry
    {
        public static function get_instance(): self
        {
            return new self();
        }

        /**
         * @return array<string, mixed>|null
         */
        public function get_registered(string $name): ?array
        {
            return ['name' => $name, 'content' => '<!-- wp:theme/section /-->'];
        }
    }
}

// PageSeeder looks for the sample page of `wp core install`, which a stub site has not.
if (!function_exists('get_page_by_path')) {
    function get_page_by_path(string $path, string $output = 'OBJECT', string|array $postType = 'page'): ?WP_Post
    {
        return null;
    }
}

// PageSeeder sets the About thumbnail when it finds the studio photographs.
if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail(int $post, int $thumbnailId): bool
    {
        return true;
    }
}

beforeEach(fn() => wp_stub_reset());

describe('Seeders', function () {
    it('run in the order the content depends on', function () {
        $seeders = [
            MediaSeeder::class,
            ProjectSeeder::class,
            PressSeeder::class,
            PageSeeder::class,
            JournalSeeder::class,
            TestimonialSeeder::class,
            SettingsSeeder::class,
            MenuSeeder::class,
            LanguageSeeder::class,
        ];

        $orders = [];

        foreach ($seeders as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsSeeder::class);

            expect($attributes)->toHaveCount(1);
            expect($reflection->implementsInterface(SeederInterface::class))->toBeTrue();
            expect($attributes[0]->newInstance()->description)->not->toBe('');

            $orders[] = $attributes[0]->newInstance()->order;
        }

        $sorted = $orders;
        sort($sorted);

        expect($orders)->toBe($sorted);
        expect(array_unique($orders))->toHaveCount(count($orders));
    });

    it('find a photograph for every file in credits.json, in a series that exists', function () {
        $credits = Photographs::credits();
        $series = [...array_keys(new ReflectionClass(ProjectSeeder::class)->getConstant('PROJECTS')), '_studio'];

        expect($credits)->toHaveCount(30);

        foreach ($credits as $entry) {
            expect(Photographs::directory() . '/' . $entry['file'])->toBeFile();
            expect($series)->toContain($entry['project']);
        }
    });

    it('write the pages by slug, the Legal page the footer links to included', function () {
        new PageSeeder()->run();

        $posts = array_column(array_column(wp_stub_get_calls('wp_insert_post'), 'args'), 'postarr');

        expect(array_column($posts, 'post_name'))->toBe(['home', 'about', 'journal', 'legal', 'accessibility-draft']);

        // The About page is block markup: the hero, the reading-time binding and the
        // commission pattern are on a page a visitor sees.
        $about = $posts[1]['post_content'];

        expect($about)->toContain('<!-- wp:theme/hero {"title":"About"');
        expect($about)->toContain('"source":"theme/reading-time"');
        expect($about)->toContain('<!-- wp:theme/section /-->');

        // Two scheduled paragraphs: one visible now, one from a date in the future.
        expect($about)->toContain(
            '<!-- wp:paragraph {"visibleFrom":"2026-01-01T09:00","visibleUntil":"2099-01-01T09:00"} -->',
        );
        expect($about)->toContain('<!-- wp:paragraph {"visibleFrom":"2099-01-01T09:00"} -->');
    });

    it('write a draft that the pre-publish checks refuse to publish', function () {
        // The photographs of the studio, found by their Unsplash id; no page exists yet.
        $GLOBALS['wp_stub_get_posts'] = static fn(array $args): array => (
            ($args['post_type'] ?? '') === 'attachment' ? [7] : []
        );
        $GLOBALS['wp_stub_attachments'][7] = ['url' => 'https://example.test/studio.jpg'];

        try {
            new PageSeeder()->run();
        } finally {
            unset($GLOBALS['wp_stub_get_posts']);
            $GLOBALS['wp_stub_attachments'] = [];
        }

        $posts = array_column(array_column(wp_stub_get_calls('wp_insert_post'), 'args'), 'postarr');
        $draft = array_values(array_filter(
            $posts,
            static fn(array $post): bool => $post['post_name'] === 'accessibility-draft',
        ))[0];

        expect($draft['post_status'])->toBe('draft');

        $content = $draft['post_content'];

        // Two level 1 headings: the hero and a core heading.
        expect($content)->toContain('<!-- wp:theme/hero {"title":"Accessibility draft","headingLevel":1');
        expect($content)->toContain('<!-- wp:heading {"level":1} -->');
        // A jump from h2 to h4.
        expect($content)->toContain('<!-- wp:heading {"level":2} -->');
        expect($content)->toContain('<!-- wp:heading {"level":4} -->');
        // An image with no alternative text.
        expect($content)->toContain('<!-- wp:image {"id":7,"sizeSlug":"large"} -->');
        expect($content)->toContain('<img src="https://example.test/studio.jpg" alt="" class="wp-image-7"/>');
    });

    it('write the testimonials by slug', function () {
        new TestimonialSeeder()->run();

        $posts = array_column(array_column(wp_stub_get_calls('wp_insert_post'), 'args'), 'postarr');

        expect(array_column($posts, 'post_name'))->toBe(['water-journal', 'grille', 'contrejour']);
        expect(array_unique(array_column($posts, 'post_type')))->toBe(['testimonial']);
    });
});
