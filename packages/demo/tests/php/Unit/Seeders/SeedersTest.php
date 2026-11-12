<?php

declare(strict_types=1);

use Demo\Seeders\MediaSeeder;
use Demo\Seeders\MenuSeeder;
use Demo\Seeders\PageSeeder;
use Demo\Seeders\Photographs;
use Demo\Seeders\ProjectSeeder;
use Demo\Seeders\SettingsSeeder;
use Demo\Seeders\TestimonialSeeder;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

beforeEach(fn() => wp_stub_reset());

describe('Seeders', function () {
    it('run in the order the content depends on', function () {
        $seeders = [
            MediaSeeder::class,
            ProjectSeeder::class,
            PageSeeder::class,
            TestimonialSeeder::class,
            SettingsSeeder::class,
            MenuSeeder::class,
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

        expect(array_column($posts, 'post_name'))->toBe(['home', 'about', 'legal']);
    });

    it('write the testimonials by slug', function () {
        new TestimonialSeeder()->run();

        $posts = array_column(array_column(wp_stub_get_calls('wp_insert_post'), 'args'), 'postarr');

        expect(array_column($posts, 'post_name'))->toBe(['water-journal', 'grille', 'contrejour']);
        expect(array_unique(array_column($posts, 'post_type')))->toBe(['testimonial']);
    });
});
