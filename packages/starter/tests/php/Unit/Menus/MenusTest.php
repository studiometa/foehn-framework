<?php

declare(strict_types=1);

use App\Menus\FooterMenu;
use App\Menus\HeaderMenu;
use App\Menus\LegalMenu;
use Studiometa\Foehn\Attributes\AsMenu;

describe('Menus', function () {
    it('HeaderMenu has correct attribute', function () {
        $ref = new ReflectionClass(HeaderMenu::class);
        $attrs = $ref->getAttributes(AsMenu::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->location)->toBe('header');
        expect($attr->description)->toBe('Menu principal');
    });

    it('FooterMenu has correct attribute', function () {
        $ref = new ReflectionClass(FooterMenu::class);
        $attrs = $ref->getAttributes(AsMenu::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->location)->toBe('footer');
        expect($attr->description)->toBe('Menu footer');
    });

    it('LegalMenu has correct attribute', function () {
        $ref = new ReflectionClass(LegalMenu::class);
        $attrs = $ref->getAttributes(AsMenu::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->location)->toBe('legal');
        expect($attr->description)->toBe('Mentions légales');
    });
});
