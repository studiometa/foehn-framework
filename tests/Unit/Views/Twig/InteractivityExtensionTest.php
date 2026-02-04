<?php

declare(strict_types=1);

use Studiometa\WPTempest\Views\Twig\InteractivityExtension;

describe('InteractivityExtension', function () {
    beforeEach(function () {
        $this->extension = new InteractivityExtension();
    });

    it('has a name', function () {
        expect($this->extension->getName())->toBe('wp_interactivity');
    });

    it('registers functions', function () {
        $functions = $this->extension->getFunctions();
        $names = array_map(fn($f) => $f->getName(), $functions);

        expect($names)->toContain('wp_interactive');
        expect($names)->toContain('wp_context');
        expect($names)->toContain('wp_directive');
        expect($names)->toContain('wp_bind');
        expect($names)->toContain('wp_on');
        expect($names)->toContain('wp_class');
        expect($names)->toContain('wp_text');
    });

    it('registers filters', function () {
        $filters = $this->extension->getFilters();
        $names = array_map(fn($f) => $f->getName(), $filters);

        expect($names)->toContain('wp_context');
    });

    describe('wpInteractive', function () {
        it('generates data-wp-interactive attribute', function () {
            $result = $this->extension->wpInteractive('theme/counter');

            expect($result)->toBe('data-wp-interactive="theme/counter"');
        });
    });

    describe('wpContext', function () {
        it('generates data-wp-context attribute with JSON', function () {
            $result = $this->extension->wpContext(['count' => 0, 'step' => 1]);

            expect($result)->toContain("data-wp-context='");
            expect($result)->toContain('"count":0');
            expect($result)->toContain('"step":1');
        });

        it('escapes quotes in JSON', function () {
            $result = $this->extension->wpContext(['text' => "it's a \"test\""]);

            // Should use hex escaping for inner quotes, outer quote is the attribute delimiter
            expect($result)->toContain('\u0027'); // Escaped single quote
            expect($result)->toContain('\u0022'); // Escaped double quote
        });
    });

    describe('wpDirective', function () {
        it('generates any directive attribute', function () {
            $result = $this->extension->wpDirective('on--click', 'actions.handleClick');

            expect($result)->toBe('data-wp-on--click="actions.handleClick"');
        });
    });

    describe('wpBind', function () {
        it('generates data-wp-bind attribute', function () {
            $result = $this->extension->wpBind('disabled', 'context.isDisabled');

            expect($result)->toBe('data-wp-bind--disabled="context.isDisabled"');
        });
    });

    describe('wpOn', function () {
        it('generates data-wp-on attribute', function () {
            $result = $this->extension->wpOn('click', 'actions.increment');

            expect($result)->toBe('data-wp-on--click="actions.increment"');
        });
    });

    describe('wpClass', function () {
        it('generates data-wp-class attribute', function () {
            $result = $this->extension->wpClass('is-active', 'context.active');

            expect($result)->toBe('data-wp-class--is-active="context.active"');
        });
    });

    describe('wpText', function () {
        it('generates data-wp-text attribute', function () {
            $result = $this->extension->wpText('context.count');

            expect($result)->toBe('data-wp-text="context.count"');
        });
    });

    describe('filterWpContext', function () {
        it('converts array to JSON string', function () {
            $result = $this->extension->filterWpContext(['foo' => 'bar', 'count' => 42]);

            expect($result)->toBe('{"foo":"bar","count":42}');
        });

        it('returns empty array for empty array', function () {
            $result = $this->extension->filterWpContext([]);

            // Empty PHP array encodes to empty JSON array
            expect($result)->toBe('[]');
        });
    });
});
