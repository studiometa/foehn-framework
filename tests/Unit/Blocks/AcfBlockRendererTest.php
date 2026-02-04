<?php

declare(strict_types=1);

use Studiometa\WPTempest\Blocks\AcfBlockRenderer;
use Studiometa\WPTempest\Contracts\AcfBlockInterface;

describe('AcfBlockRenderer', function () {
    it('renders a block with composed context', function () {
        $renderer = new AcfBlockRenderer();

        $block = new class implements AcfBlockInterface {
            public static function fields(): \StoutLogic\AcfBuilder\FieldsBuilder
            {
                return new \StoutLogic\AcfBuilder\FieldsBuilder('test');
            }

            public function compose(array $block, array $fields): array
            {
                return [
                    'title' => $fields['title'] ?? 'Default',
                    'custom' => 'value',
                ];
            }

            public function render(array $context, bool $isPreview = false): string
            {
                return sprintf('<div>%s - %s</div>', $context['title'], $context['custom']);
            }
        };

        $blockData = [
            'id' => 'block_123',
            'name' => 'acf/test',
            'data' => [
                'title' => 'Hello World',
            ],
        ];

        $result = $renderer->render($block, $blockData, false);

        expect($result)->toBe('<div>Hello World - value</div>');
    });

    it('enriches context with block metadata', function () {
        $renderer = new AcfBlockRenderer();
        $capturedContext = [];

        $block = new class($capturedContext) implements AcfBlockInterface {
            public function __construct(
                private array &$captured,
            ) {}

            public static function fields(): \StoutLogic\AcfBuilder\FieldsBuilder
            {
                return new \StoutLogic\AcfBuilder\FieldsBuilder('test');
            }

            public function compose(array $block, array $fields): array
            {
                return [];
            }

            public function render(array $context, bool $isPreview = false): string
            {
                $this->captured = $context;
                return '';
            }
        };

        $blockData = [
            'id' => 'block_456',
            'name' => 'acf/hero',
            'align' => 'wide',
            'anchor' => 'my-anchor',
            'className' => 'custom-class',
            'data' => [],
        ];

        $renderer->render($block, $blockData, true);

        expect($capturedContext['block_id'])->toBe('block_456');
        expect($capturedContext['block_name'])->toBe('acf/hero');
        expect($capturedContext['is_preview'])->toBeTrue();
        expect($capturedContext['align'])->toBe('wide');
        expect($capturedContext['anchor'])->toBe('my-anchor');
        expect($capturedContext['block_class'])->toContain('wp-block-acf-hero');
        expect($capturedContext['block_class'])->toContain('alignwide');
        expect($capturedContext['block_class'])->toContain('custom-class');
    });

    it('parses ACF data format correctly', function () {
        $renderer = new AcfBlockRenderer();
        $capturedFields = [];

        $block = new class($capturedFields) implements AcfBlockInterface {
            public function __construct(
                private array &$captured,
            ) {}

            public static function fields(): \StoutLogic\AcfBuilder\FieldsBuilder
            {
                return new \StoutLogic\AcfBuilder\FieldsBuilder('test');
            }

            public function compose(array $block, array $fields): array
            {
                $this->captured = $fields;
                return [];
            }

            public function render(array $context, bool $isPreview = false): string
            {
                return '';
            }
        };

        $blockData = [
            'id' => 'block_789',
            'name' => 'acf/test',
            'data' => [
                'title' => 'My Title',
                '_title' => 'field_abc123', // Field key reference, should be skipped
                'content' => 'My Content',
                '_content' => 'field_def456',
                'field_abc123' => 'raw_value', // Raw field key, should be skipped
            ],
        ];

        $renderer->render($block, $blockData, false);

        expect($capturedFields)->toBe([
            'title' => 'My Title',
            'content' => 'My Content',
        ]);
    });

    it('handles empty block data gracefully', function () {
        $renderer = new AcfBlockRenderer();

        $block = new class implements AcfBlockInterface {
            public static function fields(): \StoutLogic\AcfBuilder\FieldsBuilder
            {
                return new \StoutLogic\AcfBuilder\FieldsBuilder('test');
            }

            public function compose(array $block, array $fields): array
            {
                return ['fields_count' => count($fields)];
            }

            public function render(array $context, bool $isPreview = false): string
            {
                return (string) $context['fields_count'];
            }
        };

        $blockData = [
            'name' => 'acf/empty',
        ];

        $result = $renderer->render($block, $blockData, false);

        expect($result)->toBe('0');
    });
});
