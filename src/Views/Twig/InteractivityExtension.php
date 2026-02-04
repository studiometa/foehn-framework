<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for WordPress Interactivity API.
 *
 * Provides helper functions and filters for working with
 * the WordPress Interactivity API in Twig templates.
 */
final class InteractivityExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'wp_interactivity';
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('wp_interactive', [$this, 'wpInteractive'], ['is_safe' => ['html']]),
            new TwigFunction('wp_context', [$this, 'wpContext'], ['is_safe' => ['html']]),
            new TwigFunction('wp_directive', [$this, 'wpDirective'], ['is_safe' => ['html']]),
            new TwigFunction('wp_bind', [$this, 'wpBind'], ['is_safe' => ['html']]),
            new TwigFunction('wp_on', [$this, 'wpOn'], ['is_safe' => ['html']]),
            new TwigFunction('wp_class', [$this, 'wpClass'], ['is_safe' => ['html']]),
            new TwigFunction('wp_text', [$this, 'wpText'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('wp_context', [$this, 'filterWpContext'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Generate data-wp-interactive attribute.
     *
     * @param string $namespace Interactivity namespace
     * @return string HTML attribute
     */
    public function wpInteractive(string $namespace): string
    {
        return sprintf('data-wp-interactive="%s"', $this->escAttr($namespace));
    }

    /**
     * Generate data-wp-context attribute.
     *
     * @param array<string, mixed> $context Context data
     * @return string HTML attribute
     */
    public function wpContext(array $context): string
    {
        $json = json_encode($context, JSON_HEX_APOS | JSON_HEX_QUOT);

        return sprintf("data-wp-context='%s'", $json);
    }

    /**
     * Generate any wp directive attribute.
     *
     * @param string $directive Directive name (e.g., 'on--click', 'bind--disabled')
     * @param string $value Directive value
     * @return string HTML attribute
     */
    public function wpDirective(string $directive, string $value): string
    {
        return sprintf('data-wp-%s="%s"', $this->escAttr($directive), $this->escAttr($value));
    }

    /**
     * Generate data-wp-bind--{attribute} directive.
     *
     * @param string $attribute HTML attribute to bind
     * @param string $value Binding expression
     * @return string HTML attribute
     */
    public function wpBind(string $attribute, string $value): string
    {
        return sprintf('data-wp-bind--%s="%s"', $this->escAttr($attribute), $this->escAttr($value));
    }

    /**
     * Generate data-wp-on--{event} directive.
     *
     * @param string $event Event name (e.g., 'click', 'submit')
     * @param string $action Action expression (e.g., 'actions.handleClick')
     * @return string HTML attribute
     */
    public function wpOn(string $event, string $action): string
    {
        return sprintf('data-wp-on--%s="%s"', $this->escAttr($event), $this->escAttr($action));
    }

    /**
     * Generate data-wp-class--{class} directive.
     *
     * @param string $class CSS class name
     * @param string $condition Condition expression
     * @return string HTML attribute
     */
    public function wpClass(string $class, string $condition): string
    {
        return sprintf('data-wp-class--%s="%s"', $this->escAttr($class), $this->escAttr($condition));
    }

    /**
     * Generate data-wp-text directive.
     *
     * @param string $expression Text binding expression
     * @return string HTML attribute
     */
    public function wpText(string $expression): string
    {
        return sprintf('data-wp-text="%s"', $this->escAttr($expression));
    }

    /**
     * Filter to convert array to JSON context string.
     *
     * @param array<string, mixed> $context Context data
     * @return string JSON string
     */
    public function filterWpContext(array $context): string
    {
        return json_encode($context, JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
    }

    /**
     * Escape attribute value.
     *
     * @param string $value
     * @return string
     */
    private function escAttr(string $value): string
    {
        if (function_exists('esc_attr')) {
            return esc_attr($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
