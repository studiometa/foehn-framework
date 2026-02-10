<?php

declare(strict_types=1);

use Studiometa\Foehn\Views\Twig\BlockMarkupExtension;
use Twig\Extension\AbstractExtension;

describe('BlockMarkupExtension', function () {
    beforeEach(function () {
        $this->extension = new BlockMarkupExtension();
    });

    it('extends AbstractExtension', function () {
        expect($this->extension)->toBeInstanceOf(AbstractExtension::class);
    });

    it('has a name', function () {
        expect($this->extension->getName())->toBe('wp_block_markup');
    });

    it('registers functions', function () {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        expect($names)->toContain('wp_block_start');
        expect($names)->toContain('wp_block_end');
        expect($names)->toContain('wp_block');
    });

    describe('blockStart', function () {
        it('generates opening comment without attributes', function () {
            $result = $this->extension->blockStart('heading');

            expect($result)->toBe('<!-- wp:heading -->');
        });

        it('generates opening comment with attributes', function () {
            $result = $this->extension->blockStart('paragraph', ['align' => 'center']);

            expect($result)->toBe('<!-- wp:paragraph {"align":"center"} -->');
        });

        it('handles nested attributes', function () {
            $result = $this->extension->blockStart('group', [
                'layout' => ['type' => 'constrained'],
            ]);

            expect($result)->toBe('<!-- wp:group {"layout":{"type":"constrained"}} -->');
        });

        it('handles multiple attributes', function () {
            $result = $this->extension->blockStart('button', [
                'backgroundColor' => 'primary',
                'textColor' => 'white',
            ]);

            expect($result)->toBe('<!-- wp:button {"backgroundColor":"primary","textColor":"white"} -->');
        });

        it('preserves namespaced block names', function () {
            $result = $this->extension->blockStart('theme/hero');

            expect($result)->toBe('<!-- wp:theme/hero -->');
        });

        it('preserves core namespaced block names', function () {
            $result = $this->extension->blockStart('core/heading');

            expect($result)->toBe('<!-- wp:core/heading -->');
        });
    });

    describe('blockEnd', function () {
        it('generates closing comment', function () {
            $result = $this->extension->blockEnd('heading');

            expect($result)->toBe('<!-- /wp:heading -->');
        });

        it('preserves namespaced block names', function () {
            $result = $this->extension->blockEnd('theme/hero');

            expect($result)->toBe('<!-- /wp:theme/hero -->');
        });
    });

    describe('block', function () {
        it('generates complete block markup', function () {
            $result = $this->extension->block('paragraph', [], '<p>Hello world</p>');

            expect($result)->toBe("<!-- wp:paragraph -->\n<p>Hello world</p>\n<!-- /wp:paragraph -->");
        });

        it('generates block with attributes', function () {
            $result = $this->extension->block('heading', ['level' => 2], '<h2>Title</h2>');

            expect($result)->toBe("<!-- wp:heading {\"level\":2} -->\n<h2>Title</h2>\n<!-- /wp:heading -->");
        });

        it('generates block with empty content', function () {
            $result = $this->extension->block('separator');

            expect($result)->toBe("<!-- wp:separator -->\n\n<!-- /wp:separator -->");
        });
    });
});
