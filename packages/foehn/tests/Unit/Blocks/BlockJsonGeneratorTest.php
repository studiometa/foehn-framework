<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Blocks\BlockJsonGenerator;
use Studiometa\Foehn\Contracts\BlockInterface;

final class EmptyBlockStub implements BlockInterface
{
    public static function attributes(): array
    {
        return [];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '';
    }
}

final class UiKeysBlockStub implements BlockInterface
{
    public static function attributes(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'default' => 'Hello',
                'control' => 'text',
                'label' => 'The title',
                'help' => 'Some help',
            ],
            'variant' => [
                'type' => 'string',
                'options' => ['light' => 'Light'],
            ],
        ];
    }

    public function compose(array $attributes, string $content, WP_Block $block): array
    {
        return [];
    }

    public function render(array $attributes, string $content, WP_Block $block): string
    {
        return '';
    }
}

describe('BlockJsonGenerator', function () {
    it('generates minimal block.json', function () {
        $generator = new BlockJsonGenerator();
        $attribute = new AsBlock(name: 'theme/simple', title: 'Simple Block');

        $className = new class implements BlockInterface {
            public static function attributes(): array
            {
                return [];
            }

            public function compose(array $attributes, string $content, WP_Block $block): array
            {
                return [];
            }

            public function render(array $attributes, string $content, WP_Block $block): string
            {
                return '';
            }
        };

        $json = $generator->generate($attribute, $className::class);

        expect($json['$schema'])->toBe('https://schemas.wp.org/trunk/block.json');
        expect($json['apiVersion'])->toBe(3);
        expect($json['name'])->toBe('theme/simple');
        expect($json['title'])->toBe('Simple Block');
        expect($json['category'])->toBe('widgets');
        expect($json['textdomain'])->toBe('theme');
    });

    it('generates full block.json with all options', function () {
        $generator = new BlockJsonGenerator();
        $attribute = new AsBlock(
            name: 'theme/full',
            title: 'Full Block',
            category: 'layout',
            icon: 'star',
            description: 'A full featured block',
            keywords: ['full', 'featured'],
            supports: ['align' => true],
            parent: 'theme/parent',
            ancestor: ['theme/ancestor'],
            interactivity: true,
        );

        $className = new class implements BlockInterface {
            public static function attributes(): array
            {
                return [
                    'count' => [
                        'type' => 'number',
                        'default' => 0,
                    ],
                ];
            }

            public function compose(array $attributes, string $content, WP_Block $block): array
            {
                return [];
            }

            public function render(array $attributes, string $content, WP_Block $block): string
            {
                return '';
            }
        };

        $json = $generator->generate($attribute, $className::class);

        expect($json['icon'])->toBe('star');
        expect($json['description'])->toBe('A full featured block');
        expect($json['keywords'])->toBe(['full', 'featured']);
        expect($json['supports'])->toHaveKey('align');
        expect($json['supports'])->toHaveKey('interactivity');
        expect($json['supports']['html'])->toBeFalse();
        expect($json['parent'])->toBe(['theme/parent']);
        expect($json['ancestor'])->toBe(['theme/ancestor']);
        expect($json['attributes'])->toHaveKey('count');
    });

    it('extracts text domain from block name', function () {
        $generator = new BlockJsonGenerator();

        $attribute1 = new AsBlock(name: 'theme/block', title: 'Block');
        $className = new class implements BlockInterface {
            public static function attributes(): array
            {
                return [];
            }

            public function compose(array $attributes, string $content, WP_Block $block): array
            {
                return [];
            }

            public function render(array $attributes, string $content, WP_Block $block): string
            {
                return '';
            }
        };

        $json1 = $generator->generate($attribute1, $className::class);
        expect($json1['textdomain'])->toBe('theme');

        $attribute2 = new AsBlock(name: 'starter/block', title: 'Block');
        $json2 = $generator->generate($attribute2, $className::class);
        expect($json2['textdomain'])->toBe('starter');
    });

    it('emits allowedBlocks but not the inner blocks template nor the template lock', function () {
        $generator = new BlockJsonGenerator();
        $attribute = new AsBlock(
            name: 'theme/section',
            title: 'Section',
            allowedBlocks: ['core/heading', 'core/paragraph'],
            innerBlocksTemplate: [['core/heading', ['level' => 2]]],
            innerBlocksTemplateLock: 'insert',
        );

        $json = $generator->generate($attribute, EmptyBlockStub::class);

        expect($json['allowedBlocks'])->toBe(['core/heading', 'core/paragraph']);
        expect($json)->not->toHaveKey('innerBlocksTemplate');
        expect($json)->not->toHaveKey('template');
        expect($json)->not->toHaveKey('templateLock');
    });

    it('omits allowedBlocks for a non container block', function () {
        $generator = new BlockJsonGenerator();
        $attribute = new AsBlock(name: 'theme/simple', title: 'Simple');

        $json = $generator->generate($attribute, EmptyBlockStub::class);

        expect($json)->not->toHaveKey('allowedBlocks');
    });

    it('disables the html support so "Edit as HTML" cannot invalidate a dynamic block', function () {
        $generator = new BlockJsonGenerator();
        $attribute = new AsBlock(name: 'theme/simple', title: 'Simple');

        $json = $generator->generate($attribute, EmptyBlockStub::class);

        expect($json['supports'])->toBe(['html' => false]);
    });

    it('lets an explicit html support win over the seeded one', function () {
        $generator = new BlockJsonGenerator();
        $attribute = new AsBlock(name: 'theme/simple', title: 'Simple', supports: ['html' => true]);

        $json = $generator->generate($attribute, EmptyBlockStub::class);

        expect($json['supports']['html'])->toBeTrue();
    });

    it('strips the editor only attribute keys', function () {
        $generator = new BlockJsonGenerator();
        $attribute = new AsBlock(name: 'theme/card', title: 'Card');

        $json = $generator->generate($attribute, UiKeysBlockStub::class);

        expect($json['attributes']['title'])->toBe(['type' => 'string', 'default' => 'Hello']);
        expect($json['attributes']['title'])->not->toHaveKey('control');
        expect($json['attributes']['title'])->not->toHaveKey('label');
        expect($json['attributes']['title'])->not->toHaveKey('help');
        expect($json['attributes']['variant'])->not->toHaveKey('options');
    });
});
