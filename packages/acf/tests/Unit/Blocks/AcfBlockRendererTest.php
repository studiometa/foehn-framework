<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\AcfBlockRenderer;
use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Config\AcfConfig;
use Studiometa\Foehn\Contracts\AcfBlockInterface;
use Studiometa\Foehn\Contracts\Arrayable;
use Timber\Integration\AcfIntegration;

beforeEach(function () {
    wp_stub_reset();
});

/**
 * A block that hands the fields it receives to the test.
 */
function capturingAcfBlock(?array &$captured): AcfBlockInterface
{
    return new class($captured) implements AcfBlockInterface {
        public function __construct(
            private ?array &$captured,
        ) {}

        public static function fields(): \StoutLogic\AcfBuilder\FieldsBuilder
        {
            return new \StoutLogic\AcfBuilder\FieldsBuilder('test');
        }

        public function compose(array $block, array $fields): array
        {
            $this->captured = $fields;

            return $fields;
        }

        public function render(array $context, bool $isPreview = false): string
        {
            return '';
        }
    };
}

describe('AcfBlockRenderer', function () {
    it('renders a block with composed context', function () {
        $GLOBALS['wp_stub_acf_fields']['block_123'] = ['title' => 'Hello World'];
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
                '_title' => 'field_title',
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

    it('reads the fields through get_fields() for the block ID', function () {
        $GLOBALS['wp_stub_acf_fields']['block_789'] = [
            'title' => 'My Title',
            'meta' => ['label' => 'Group label'],
            'items' => [['heading' => 'First'], ['heading' => 'Second']],
        ];
        $renderer = new AcfBlockRenderer(new AcfConfig(transformFields: false));
        $capturedFields = null;

        $renderer->render(capturingAcfBlock($capturedFields), [
            'id' => 'block_789',
            'name' => 'acf/test',
            // What ACF stores in the block comment: flat, with field key references.
            'data' => [
                'title' => 'My Title',
                '_title' => 'field_title',
                'meta_label' => 'Group label',
                '_meta_label' => 'field_meta_label',
                'items_0_heading' => 'First',
                'items_1_heading' => 'Second',
                'items' => 2,
            ],
        ]);

        expect($capturedFields)->toBe([
            'title' => 'My Title',
            'meta' => ['label' => 'Group label'],
            'items' => [['heading' => 'First'], ['heading' => 'Second']],
        ]);
        expect(wp_stub_get_calls('get_fields'))->toBe([
            ['function' => 'get_fields', 'args' => ['postId' => 'block_789', 'formatValue' => true]],
        ]);
    });

    it('passes no fields when the block has no ID', function () {
        $capturedFields = null;

        new AcfBlockRenderer()->render(capturingAcfBlock($capturedFields), [
            'name' => 'acf/empty',
            'data' => ['title' => 'Raw'],
        ]);

        expect($capturedFields)->toBe([]);
        expect(wp_stub_get_calls('get_fields'))->toBe([]);
    });

    it('passes no fields when ACF has none for the block', function () {
        $capturedFields = null;

        new AcfBlockRenderer()->render(capturingAcfBlock($capturedFields), [
            'id' => 'block_none',
            'name' => 'acf/test',
        ]);

        expect($capturedFields)->toBe([]);
    });

    it('flattens Arrayable DTOs from compose() to array', function () {
        $renderer = new AcfBlockRenderer();
        $capturedContext = [];

        $dto = new class('Hello', 'https://example.com') implements Arrayable {
            use HasToArray;

            public function __construct(
                public string $title,
                public string $imageUrl,
            ) {}
        };

        $block = new class($capturedContext, $dto) implements AcfBlockInterface {
            public function __construct(
                private array &$captured,
                private Arrayable $dto,
            ) {}

            public static function fields(): \StoutLogic\AcfBuilder\FieldsBuilder
            {
                return new \StoutLogic\AcfBuilder\FieldsBuilder('test');
            }

            public function compose(array $block, array $fields): Arrayable
            {
                return $this->dto;
            }

            public function render(array $context, bool $isPreview = false): string
            {
                $this->captured = $context;
                return sprintf('%s|%s', $context['title'], $context['image_url']);
            }
        };

        $blockData = [
            'id' => 'block_dto',
            'name' => 'acf/dto-test',
            'data' => [],
        ];

        $result = $renderer->render($block, $blockData, false);

        // DTO properties flattened to snake_case keys
        expect($result)->toBe('Hello|https://example.com');
        // Block metadata still enriched
        expect($capturedContext['block_id'])->toBe('block_dto');
        expect($capturedContext['is_preview'])->toBeFalse();
    });
});

describe('AcfBlockRenderer field transformation', function () {
    it("swaps ACF's formatting for Timber's transforms while it reads the fields", function () {
        $imageType = new acf_field();
        $GLOBALS['wp_stub_acf_field_types']['image'] = $imageType;
        $GLOBALS['wp_stub_acf_fields']['block_123'] = ['image' => 42];
        $capturedFields = null;

        new AcfBlockRenderer()->render(capturingAcfBlock($capturedFields), ['id' => 'block_123', 'name' => 'acf/test']);

        $calls = array_values(array_filter(
            $GLOBALS['wp_stub_calls'],
            fn(array $call) => (
                $call['function'] === 'get_fields'
                || ($call['args']['hook'] ?? null) === 'acf/format_value/type=image'
            ),
        ));

        expect(array_map(fn(array $call) => [$call['function'], $call['args']['callback'] ?? null], $calls))->toBe([
            ['remove_filter', [$imageType, 'format_value']],
            ['add_filter', [AcfIntegration::class, 'transform_image']],
            ['get_fields', null],
            ['remove_filter', [AcfIntegration::class, 'transform_image']],
            ['add_filter', [$imageType, 'format_value']],
        ]);
    });

    it('leaves ACF formatting alone when transformFields is disabled', function () {
        $GLOBALS['wp_stub_acf_field_types']['image'] = new acf_field();
        $GLOBALS['wp_stub_acf_fields']['block_123'] = ['image' => 42, 'text' => 'Hello'];
        $renderer = new AcfBlockRenderer(new AcfConfig(transformFields: false));
        $capturedFields = null;

        $renderer->render(capturingAcfBlock($capturedFields), ['id' => 'block_123', 'name' => 'acf/test']);

        expect($capturedFields)->toBe(['image' => 42, 'text' => 'Hello']);
        expect(wp_stub_get_calls('add_filter'))->toBe([]);
        expect(wp_stub_get_calls('remove_filter'))->toBe([]);
    });

    it('skips the field types ACF does not register', function () {
        $GLOBALS['wp_stub_acf_fields']['block_123'] = ['title' => 'Test'];
        $capturedFields = null;

        new AcfBlockRenderer()->render(capturingAcfBlock($capturedFields), ['id' => 'block_123', 'name' => 'acf/test']);

        expect($capturedFields)->toBe(['title' => 'Test']);
        expect(wp_stub_get_calls('add_filter'))->toBe([]);
    });

    it('forgets the formatted values ACF cached for the block', function () {
        $store = acf_get_store('values');
        $store->data = [
            'block_123:image:formatted' => 'transformed',
            'block_123:items_0_picture:formatted' => 'transformed',
            'block_123:image' => 42,
            'block_456:image:formatted' => 'other block',
        ];
        $GLOBALS['wp_stub_acf_fields']['block_123'] = ['image' => 42];
        $capturedFields = null;

        new AcfBlockRenderer()->render(capturingAcfBlock($capturedFields), ['id' => 'block_123', 'name' => 'acf/test']);

        expect($store->data)->toBe([
            'block_123:image' => 42,
            'block_456:image:formatted' => 'other block',
        ]);
    });
});
