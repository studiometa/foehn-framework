<?php

declare(strict_types=1);

use Demo\Shortcodes\EditionShortcode;
use Studiometa\Foehn\Attributes\AsShortcode;

describe('EditionShortcode', function () {
    it('declares the edition shortcode on its render method', function () {
        $attrs = new ReflectionMethod(EditionShortcode::class, 'render')->getAttributes(AsShortcode::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->tag)->toBe('edition');
    });

    it('renders its view with the edition terms', function () {
        $rendered = [];
        $shortcode = new EditionShortcode(createFakeViewEngine(function (string $template, array $context) use (
            &$rendered,
        ) {
            $rendered = [$template, $context];

            return 'ok';
        }));

        expect($shortcode->render(['size' => '10', 'paper' => 'cotton']))->toBe('ok');
        expect($rendered)->toBe(['shortcodes/edition', ['size' => 10, 'paper' => 'cotton', 'format' => '']]);
    });
});
