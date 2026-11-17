<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Views\Twig\IconExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

describe('IconExtension', function () {
    beforeEach(function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_stylesheet'] = 'starter-theme';
    });

    it('is discovered, so templates can use its function', function () {
        expect(new ReflectionClass(IconExtension::class)->getAttributes(AsTwigExtension::class))->toHaveCount(1);
    });

    it('prints the icon of the theme collection, unescaped', function () {
        $GLOBALS['wp_stub_icons']['starter-theme/arrow-right'] = '<svg aria-hidden="true"></svg>';
        $twig = new Environment(new ArrayLoader([
            'button' => '<button>{{ icon("arrow-right") }}</button>',
        ]));
        $twig->addExtension(new IconExtension(new FoehnConfig()));

        expect($twig->render('button'))->toBe('<button><svg aria-hidden="true"></svg></button>');
    });

    it('passes a full name and the arguments to wp_get_icon()', function () {
        $twig = new Environment(new ArrayLoader([
            'search' => '{{ icon("core/search", { size: 16, class: "icon", label: "Search" }) }}',
        ]));
        $twig->addExtension(new IconExtension(new FoehnConfig()));

        $twig->render('search');

        expect(wp_stub_get_calls('wp_get_icon')[0]['args'])->toBe([
            'name' => 'core/search',
            'args' => ['size' => 16, 'class' => 'icon', 'label' => 'Search'],
        ]);
    });

    it('prints nothing for an icon that is not registered', function () {
        expect(new IconExtension(new FoehnConfig())->icon('missing'))->toBe('');
    });
});
