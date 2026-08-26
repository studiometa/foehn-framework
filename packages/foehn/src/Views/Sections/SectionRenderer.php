<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Sections;

use Studiometa\Foehn\Contracts\ViewEngineInterface;

/**
 * Renders declared section templates and owns their stable HTML boundary.
 */
final class SectionRenderer
{
    private bool $renderingSelected = false;

    /** @var list<string> */
    private array $stack = [];

    public function __construct(
        private readonly ViewEngineInterface $view,
    ) {}

    public function isRenderingSelected(): bool
    {
        return $this->renderingSelected;
    }

    public function isRendering(): bool
    {
        return $this->stack !== [];
    }

    /** @param array<string, mixed> $context */
    public function render(string $name, array $context): string
    {
        if (in_array($name, $this->stack, true)) {
            throw new \LogicException("Section '{$name}' cannot render itself recursively.");
        }

        $this->stack[] = $name;

        try {
            $html = $this->view->render('sections/' . $name, $context);
        } finally {
            array_pop($this->stack);
        }

        $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<div id="foehn-section-%s" data-foehn-section="%s">%s</div>',
            $escapedName,
            $escapedName,
            $html,
        );
    }

    /**
     * Render every requested declaration before returning any bytes.
     *
     * @param list<string> $names
     */
    public function renderSelected(array $names, SectionCollector $collector): string
    {
        $html = '';
        $this->renderingSelected = true;

        try {
            foreach ($names as $name) {
                if (!$collector->has($name)) {
                    throw new SectionNotFoundException();
                }

                $html .= $this->render($name, $collector->context($name));
            }
        } finally {
            $this->renderingSelected = false;
        }

        return $html;
    }
}
