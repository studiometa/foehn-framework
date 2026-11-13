<?php

declare(strict_types=1);

use App\Menus\FooterMenu;
use App\Menus\HeaderMenu;
use App\Menus\LegalMenu;
use App\Seeders\MenusSeeder;
use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;

describe('MenusSeeder', function () {
    it('is a seeder with a description', function () {
        $reflection = new ReflectionClass(MenusSeeder::class);
        $attributes = $reflection->getAttributes(AsSeeder::class);

        expect($reflection->implementsInterface(SeederInterface::class))->toBeTrue();
        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->description)->not->toBe('');
    });

    it('writes a menu for each location the theme registers', function () {
        $menus = new ReflectionClass(MenusSeeder::class)->getConstant('MENUS');
        $locations = array_map(
            static fn(string $class): string => new ReflectionClass(
                $class,
            )->getAttributes(AsMenu::class)[0]->newInstance()->location,
            [HeaderMenu::class, FooterMenu::class, LegalMenu::class],
        );

        expect(array_keys($menus))->toBe($locations);
    });
});
