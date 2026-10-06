<?php

declare(strict_types=1);

use Demo\Blocks\BlockCategories;
use Demo\Blocks\HeroBlock;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Attributes\AsBlockCategory;

describe('BlockCategories', function () {
    it('declares the category the hero block is filed under', function () {
        $categories = array_map(
            static fn(ReflectionAttribute $attribute): AsBlockCategory => $attribute->newInstance(),
            new ReflectionClass(BlockCategories::class)->getAttributes(AsBlockCategory::class),
        );

        $hero = new ReflectionClass(HeroBlock::class)->getAttributes(AsBlock::class)[0]->newInstance();

        expect(array_column($categories, 'slug'))->toContain($hero->category);
    });
});
