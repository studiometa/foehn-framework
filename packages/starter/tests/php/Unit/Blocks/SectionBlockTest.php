<?php

declare(strict_types=1);

use App\Blocks\SectionBlock;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Blocks\BlockAttributeSchema;
use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;

/**
 * Self-contained fake, deliberately not the shared helper: tests/php/Pest.php is
 * not loaded by this suite, so anything global depends on file load order.
 */
function createSectionViewEngine(?Closure $renderCallback = null): ViewEngineInterface
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

describe('SectionBlock', function () {
    it('implements BlockInterface', function () {
        expect(is_subclass_of(SectionBlock::class, BlockInterface::class))->toBeTrue();
    });

    it('has AsBlock attribute with correct config', function () {
        $attrs = (new ReflectionClass(SectionBlock::class))->getAttributes(AsBlock::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('theme/section');
        expect($attr->title)->toBe('Section');
        expect($attr->category)->toBe('design');
    });

    it('is a container', function () {
        $attr = (new ReflectionClass(SectionBlock::class))->getAttributes(AsBlock::class)[0]->newInstance();

        expect(AsBlock::hasInnerBlocks(
            $attr->allowedBlocks,
            $attr->innerBlocksTemplate,
            $attr->innerBlocksTemplateLock,
        ))->toBeTrue();
    });

    it('restricts its inner blocks to the core blocks prose belongs in', function () {
        $attr = (new ReflectionClass(SectionBlock::class))->getAttributes(AsBlock::class)[0]->newInstance();

        expect($attr->allowedBlocks)->toBe(['core/heading', 'core/paragraph', 'core/image', 'theme/callout']);
        expect(array_column($attr->innerBlocksTemplate, 0))->toBe(['core/heading', 'core/paragraph']);
    });

    it('is explicitly unlocked rather than left unset', function () {
        $attr = (new ReflectionClass(SectionBlock::class))->getAttributes(AsBlock::class)[0]->newInstance();

        // false and null both mean "not locked" to an author, but only false
        // survives into the editor payload as a deliberate instruction.
        expect($attr->innerBlocksTemplateLock)->toBeFalse();
        expect($attr->innerBlocksTemplateLock)->not->toBeNull();
    });

    it('drives both of its attributes with a select control', function () {
        $fields = BlockAttributeSchema::toEditorFields(SectionBlock::attributes());

        expect(array_map(static fn(array $field): ?string => $field['control'], $fields))->toBe([
            'background' => 'select',
            'spacing' => 'select',
        ]);
    });

    it('turns a plain list of spacing choices into typed options', function () {
        $fields = BlockAttributeSchema::toEditorFields(SectionBlock::attributes());

        // A list, not a value => label map: the choices are the values themselves,
        // never their array indices.
        expect($fields['spacing']['options'])->toBe([
            ['label' => 'small', 'value' => 'small'],
            ['label' => 'medium', 'value' => 'medium'],
            ['label' => 'large', 'value' => 'large'],
        ]);
    });

    it('compose() passes the inner block content through to the template', function () {
        $block = new SectionBlock(createSectionViewEngine());

        $context = $block->compose(
            ['background' => 'dark', 'spacing' => 'large'],
            '<h2>Why this matters</h2>',
            new WP_Block(),
        );

        expect($context['background'])->toBe('dark');
        expect($context['spacing'])->toBe('large');
        expect($context['content'])->toBe('<h2>Why this matters</h2>');
    });

    it('render() delegates to the view engine', function () {
        $rendered = '';
        $view = createSectionViewEngine(function (string $template) use (&$rendered): string {
            $rendered = $template;

            return '<section>section</section>';
        });

        $output = (new SectionBlock($view))->render([], '<p>Inner</p>', new WP_Block());

        expect($output)->toBe('<section>section</section>');
        expect($rendered)->toBe('blocks/section');
    });
});
