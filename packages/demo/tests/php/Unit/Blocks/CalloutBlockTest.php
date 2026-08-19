<?php

declare(strict_types=1);

use Demo\Blocks\CalloutBlock;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Blocks\BlockAttributeSchema;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

/**
 * Self-contained fake, deliberately not the shared helper: tests/php/Pest.php is
 * not loaded by this suite, so anything global depends on file load order.
 */
function createCalloutViewEngine(?Closure $renderCallback = null): ViewEngineInterface
{
    return new class($renderCallback) implements ViewEngineInterface {
        /** @var array<string, mixed> */
        private array $shared = [];

        public function __construct(
            private readonly ?Closure $renderCallback = null,
        ) {}

        public function render(string $template, array|object $context = []): string
        {
            return $this->renderCallback ? ($this->renderCallback)($template, $context) : '';
        }

        public function renderFirst(array $templates, array|object $context = []): string
        {
            return $this->render($templates[0] ?? '', $context);
        }

        public function exists(string $template): bool
        {
            return true;
        }

        public function share(string $key, mixed $value): void
        {
            $this->shared[$key] = $value;
        }

        public function getShared(): array
        {
            return $this->shared;
        }
    };
}

describe('CalloutBlock', function () {
    it('implements BlockInterface', function () {
        expect(is_subclass_of(CalloutBlock::class, BlockInterface::class))->toBeTrue();
    });

    it('has AsBlock attribute with correct config', function () {
        $attrs = new ReflectionClass(CalloutBlock::class)->getAttributes(AsBlock::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('theme/callout');
        expect($attr->title)->toBe('Callout');
        expect($attr->category)->toBe('widgets');
        expect($attr->icon)->toBe('megaphone');
    });

    it('is not a container', function () {
        $attr = new ReflectionClass(CalloutBlock::class)->getAttributes(AsBlock::class)[0]->newInstance();

        expect(AsBlock::hasInnerBlocks(
            $attr->allowedBlocks,
            $attr->innerBlocksTemplate,
            $attr->innerBlocksTemplateLock,
        ))->toBeFalse();
    });

    it('declares one attribute per sidebar control the editor supports', function () {
        $fields = BlockAttributeSchema::toEditorFields(CalloutBlock::attributes());

        expect(array_map(static fn(array $field): ?string => $field['control'], $fields))->toBe([
            'title' => 'text',
            'body' => 'textarea',
            'tone' => 'select',
            'iconId' => 'image',
            'columns' => 'number',
            'dismissible' => 'toggle',
        ]);
    });

    it('exposes its tone choices to the editor as typed options', function () {
        $fields = BlockAttributeSchema::toEditorFields(CalloutBlock::attributes());

        expect($fields['tone']['options'])->toBe([
            ['label' => 'Information', 'value' => 'info'],
            ['label' => 'Success', 'value' => 'success'],
            ['label' => 'Warning', 'value' => 'warning'],
        ]);
    });

    it('keeps the editor-only keys out of the schema WordPress registers', function () {
        $registration = BlockAttributeSchema::toRegistration(CalloutBlock::attributes());

        foreach ($registration as $schema) {
            expect($schema)->not->toHaveKeys(['control', 'label', 'help', 'options']);
        }

        expect($registration['iconId'])->toBe(['type' => 'integer']);
    });

    it('compose() maps attributes onto template data', function () {
        $block = new CalloutBlock(createCalloutViewEngine());

        $context = $block->compose(
            ['title' => 'Frozen', 'body' => 'Until Thursday.', 'tone' => 'warning', 'columns' => 2],
            '',
            new WP_Block(),
        );

        expect($context['title'])->toBe('Frozen');
        expect($context['tone'])->toBe('warning');
        expect($context['columns'])->toBe(2);
        expect($context['dismissible'])->toBeFalse();
    });

    it('render() delegates to the view engine', function () {
        $rendered = '';
        $view = createCalloutViewEngine(function (string $template) use (&$rendered): string {
            $rendered = $template;

            return '<div>callout</div>';
        });

        $output = new CalloutBlock($view)->render(['title' => 'Test'], '', new WP_Block());

        expect($output)->toBe('<div>callout</div>');
        expect($rendered)->toBe('blocks/callout');
    });
});
