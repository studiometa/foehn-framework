<?php

declare(strict_types=1);

use Studiometa\Foehn\Hooks\Security\DisableVersionDisclosure;

beforeEach(fn() => wp_stub_reset());

describe('DisableVersionDisclosure', function () {
    it('removes the generator meta tag', function () {
        $hooks = new DisableVersionDisclosure();
        $hooks->removeGeneratorTag();

        $calls = wp_stub_get_calls('remove_action');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['hook'])->toBe('wp_head');
        expect($calls[0]['args']['callback'])->toBe('wp_generator');
    });

    it('removes version query string from scripts', function () {
        $hooks = new DisableVersionDisclosure();

        expect($hooks->removeScriptVersion('https://example.com/script.js?ver=6.4.2'))
            ->toBe('https://example.com/script.js');
    });

    it('removes version query string from styles', function () {
        $hooks = new DisableVersionDisclosure();

        expect($hooks->removeStyleVersion('https://example.com/style.css?ver=6.4.2'))
            ->toBe('https://example.com/style.css');
    });

    it('preserves other query parameters when removing ver', function () {
        $hooks = new DisableVersionDisclosure();

        expect($hooks->removeScriptVersion('https://example.com/script.js?id=123&ver=6.4.2'))
            ->toBe('https://example.com/script.js?id=123');
    });

    it('preserves URLs without ver parameter', function () {
        $hooks = new DisableVersionDisclosure();

        $url = 'https://example.com/script.js?id=123';

        expect($hooks->removeScriptVersion($url))->toBe($url);
    });

    it('returns empty string for RSS generator', function () {
        $hooks = new DisableVersionDisclosure();

        expect($hooks->removeRssGenerator())->toBe('');
    });

    it('has correct attributes', function () {
        $reflection = new ReflectionClass(DisableVersionDisclosure::class);

        // init action
        $initAttrs = $reflection
            ->getMethod('removeGeneratorTag')
            ->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($initAttrs)->toHaveCount(1);
        expect($initAttrs[0]->newInstance()->hook)->toBe('init');

        // script_loader_src filter
        $scriptAttrs = $reflection
            ->getMethod('removeScriptVersion')
            ->getAttributes(\Studiometa\Foehn\Attributes\AsFilter::class);

        expect($scriptAttrs)->toHaveCount(1);
        expect($scriptAttrs[0]->newInstance()->hook)->toBe('script_loader_src');

        // style_loader_src filter
        $styleAttrs = $reflection
            ->getMethod('removeStyleVersion')
            ->getAttributes(\Studiometa\Foehn\Attributes\AsFilter::class);

        expect($styleAttrs)->toHaveCount(1);
        expect($styleAttrs[0]->newInstance()->hook)->toBe('style_loader_src');

        // the_generator filter
        $generatorAttrs = $reflection
            ->getMethod('removeRssGenerator')
            ->getAttributes(\Studiometa\Foehn\Attributes\AsFilter::class);

        expect($generatorAttrs)->toHaveCount(1);
        expect($generatorAttrs[0]->newInstance()->hook)->toBe('the_generator');
    });
});
