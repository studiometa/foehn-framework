<?php

declare(strict_types=1);

use Demo\Hooks\ThemeHooks;
use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

describe('ThemeHooks', function () {
    beforeEach(function () {
        wp_stub_reset();
    });

    it('has AsAction on setupTheme for after_setup_theme', function () {
        $ref = new ReflectionMethod(ThemeHooks::class, 'setupTheme');
        $attrs = $ref->getAttributes(AsAction::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->hook)->toBe('after_setup_theme');
    });

    it('has AsFilter on excerptLength for excerpt_length', function () {
        $ref = new ReflectionMethod(ThemeHooks::class, 'excerptLength');
        $attrs = $ref->getAttributes(AsFilter::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->hook)->toBe('excerpt_length');
    });

    it('paginates the curated project archive for the section rendering demo', function () {
        $query = new class extends WP_Query {
            public function is_post_type_archive(string $postType = ''): bool
            {
                return $postType === 'project';
            }
        };

        new ThemeHooks()->orderProjects($query);

        expect($query->get('orderby'))->toBe(['menu_order' => 'ASC', 'date' => 'DESC']);
        expect($query->get('posts_per_page'))->toBe(3);
    });

    it('does not change a secondary project query', function () {
        $query = new class extends WP_Query {
            public function is_post_type_archive(string $postType = ''): bool
            {
                return $postType === 'project';
            }
        };
        $query->set_main_query(false);

        new ThemeHooks()->orderProjects($query);

        expect($query->get_query_vars())->toBe([]);
    });

    it('excerptLength returns 30', function () {
        expect(new ThemeHooks()->excerptLength())->toBe(30);
    });

    it('has AsFilter on excerptMore for excerpt_more', function () {
        $ref = new ReflectionMethod(ThemeHooks::class, 'excerptMore');
        $attrs = $ref->getAttributes(AsFilter::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->hook)->toBe('excerpt_more');
    });

    it('excerptMore returns ellipsis', function () {
        expect(new ThemeHooks()->excerptMore())->toBe('…');
    });
});
