<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use InvalidArgumentException;
use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Views\Sections\SectionCollector;
use Studiometa\Foehn\Views\Sections\SectionRenderer;
use Studiometa\Foehn\Views\Sections\SectionRequest;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Declares page-local HTML sections and creates URLs that select them.
 */
#[AsTwigExtension]
final class SectionExtension extends AbstractExtension
{
    public function __construct(
        private readonly SectionRequest $request,
        private readonly SectionCollector $collector,
        private readonly SectionRenderer $renderer,
        private readonly PageCacheConfig $pageCacheConfig,
    ) {}

    public function getName(): string
    {
        return 'foehn_section';
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('foehn_section', $this->section(...), [
                'needs_context' => true,
                'is_safe' => ['html'],
            ]),
            new TwigFunction('foehn_section_url', $this->url(...)),
        ];
    }

    /**
     * @param array<string, mixed> $activeContext
     * @param array<string, mixed> $context
     */
    public function section(array $activeContext, string $name, array $context = [], bool $lazy = false): string
    {
        $this->assertSafeName($name);

        if ($this->renderer->isRendering()) {
            throw new \LogicException('Sections cannot be nested.');
        }

        $context = array_merge($activeContext, $context);

        if ($this->request->isSelected() && !$this->renderer->isRenderingSelected()) {
            if (in_array($name, $this->request->names(), true) && !$this->collector->declare($name, $context)) {
                throw new \LogicException("Section '{$name}' is declared more than once on this page.");
            }

            return '';
        }

        if (!$this->request->isSelected() && !$this->collector->declare($name, $context)) {
            error_log("[foehn] section '{$name}' is declared more than once; skipping the duplicate.");

            return '';
        }

        if ($lazy && !$this->request->isSelected()) {
            return $this->lazyPlaceholder($name);
        }

        return $this->renderer->render($name, $context);
    }

    public function url(string $name, ?string $targetUrl = null): string
    {
        $this->assertSafeName($name);

        [$path, $query, $fragment] = $this->urlParts($targetUrl);
        $path = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace('\\', '/', $path)) ?? '';
        $path = '/' . ltrim($path, '/');
        $ignoredQueryArgs = $this->pageCacheConfig->enabled && $this->pageCacheConfig->allowsEnvironment()
            ? $this->pageCacheConfig->getIgnoredQueryArgs()
            : [];
        $pairs = array_values(array_filter(explode('&', $query), static function (string $pair) use (
            $ignoredQueryArgs,
        ): bool {
            $parameter = explode('=', $pair, 2)[0];

            return $pair !== ''
            && $parameter !== SectionRequest::PARAMETER
            && !in_array($parameter, $ignoredQueryArgs, true);
        }));
        $pairs[] = SectionRequest::PARAMETER . '=' . rawurlencode($name);
        $url = $path . '?' . implode('&', $pairs);

        return $fragment !== '' ? $url . '#' . $fragment : $url;
    }

    /** @return array{string, string, string} */
    private function urlParts(?string $targetUrl): array
    {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/';

        if ($targetUrl === null) {
            [$uri, $fragment] = array_pad(explode('#', $currentUri, 2), 2, '');
            [$path, $query] = array_pad(explode('?', $uri, 2), 2, '');

            return [$path, $query, $fragment];
        }

        [$currentUri] = explode('#', $currentUri, 2);
        [$currentPath] = explode('?', $currentUri, 2);
        $parts = parse_url(str_replace('\\', '/', $targetUrl));
        $parts = is_array($parts) ? $parts : [];

        return [$parts['path'] ?? $currentPath, $parts['query'] ?? '', $parts['fragment'] ?? ''];
    }

    private function assertSafeName(string $name): void
    {
        if (!SectionRequest::isSafeName($name)) {
            throw new InvalidArgumentException('Invalid section name.');
        }
    }

    private function lazyPlaceholder(string $name): string
    {
        $url = htmlspecialchars($this->url($name), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<div data-component="LazyInclude" data-foehn-lazy-section data-option-src="%s"><span data-ref="error" role="alert" style="display: none">Unable to load this section.</span><span data-ref="loading" role="status">Loading…</span></div>',
            $url,
        );
    }
}
