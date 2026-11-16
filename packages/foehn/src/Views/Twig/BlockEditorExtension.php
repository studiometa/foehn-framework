<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Blocks\EditorCanvas;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig functions for blocks drawn on the block editor canvas.
 *
 * ```twig
 * {% if title or is_block_editor() %}
 *   <h2 {{ block_editable('title') }}>{{ title }}</h2>
 * {% endif %}
 * ```
 *
 * On the front end `block_editable()` prints nothing and `is_block_editor()`
 * is false, so the markup is the same as without them.
 */
#[AsTwigExtension]
final class BlockEditorExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'foehn_block_editor';
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('block_editable', $this->editable(...), ['is_safe' => ['html']]),
            new TwigFunction('is_block_editor', $this->isBlockEditor(...)),
        ];
    }

    /**
     * Mark the element the editor turns into the in-place editor of an attribute.
     *
     * @param string $attribute Name of a string attribute of the block.
     * @return string The marker attribute on the editor canvas, an empty string elsewhere.
     */
    public function editable(string $attribute): string
    {
        if (!EditorCanvas::isActive()) {
            return '';
        }

        return sprintf(
            '%s="%s"',
            EditorCanvas::EDITABLE_ATTRIBUTE,
            htmlspecialchars($attribute, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    /**
     * Whether the block is rendered for the block editor canvas.
     *
     * Use it to keep an empty editable element in the markup, so an author has
     * something to type into, or to show a state the front end reaches with
     * JavaScript, such as an open accordion item.
     */
    public function isBlockEditor(): bool
    {
        return EditorCanvas::isActive();
    }
}
