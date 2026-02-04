<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Blocks\BlockJsonGenerator;
use Studiometa\WPTempest\Contracts\BlockInterface;

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
            editorScript: 'file:./editor.js',
            editorStyle: 'file:./editor.css',
            style: 'file:./style.css',
            viewScript: 'file:./view.js',
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
        expect($json['parent'])->toBe(['theme/parent']);
        expect($json['ancestor'])->toBe(['theme/ancestor']);
        expect($json['attributes'])->toHaveKey('count');
        expect($json['editorScript'])->toBe('file:./editor.js');
        expect($json['editorStyle'])->toBe('file:./editor.css');
        expect($json['style'])->toBe('file:./style.css');
        expect($json['viewScript'])->toBe('file:./view.js');
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
});
