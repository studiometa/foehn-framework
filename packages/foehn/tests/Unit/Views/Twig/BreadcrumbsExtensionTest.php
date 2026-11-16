<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Views\Twig\BreadcrumbsExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use function Studiometa\Foehn\breadcrumbs;

describe('BreadcrumbsExtension', function () {
    beforeEach(function () {
        wp_stub_reset();
    });

    it('is discovered, so templates can use its function', function () {
        expect(new ReflectionClass(BreadcrumbsExtension::class)->getAttributes(AsTwigExtension::class))->toHaveCount(1);
    });

    it('gives the breadcrumbs to a template', function () {
        $GLOBALS['rank_math_stub_crumbs'] = [
            ['Home',  'http://example.com'],
            ['About', 'http://example.com/about/'],
        ];
        $twig = new Environment(new ArrayLoader([
            'trail' => '{% for item in breadcrumbs() %}[{{ item.label }}|{{ item.url }}]{% endfor %}',
        ]));
        $twig->addExtension(new BreadcrumbsExtension());

        expect($twig->render('trail'))->toBe('[Home|http://example.com][About|]');
    });

    it('gives what the PHP helper gives', function () {
        $GLOBALS['rank_math_stub_crumbs'] = [['Home', 'http://example.com']];

        expect(breadcrumbs())->toBe([['label' => 'Home', 'url' => null]]);
    });
});
