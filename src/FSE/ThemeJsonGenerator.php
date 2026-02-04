<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\FSE;

/**
 * Generates theme.json configuration for WordPress FSE.
 */
final class ThemeJsonGenerator
{
    private int $version = 3;

    /** @var array<string, mixed> */
    private array $settings = [];

    /** @var array<string, mixed> */
    private array $styles = [];

    /** @var list<array{name: string, title: string, postTypes: array<array-key, string>}> */
    private array $customTemplates = [];

    /** @var list<array{name: string, title: string, area: string}> */
    private array $templateParts = [];

    /** @var list<string> */
    private array $patterns = [];

    /**
     * Set theme.json settings.
     *
     * @param array<string, mixed> $settings
     * @return self
     */
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * Merge additional settings.
     *
     * @param array<string, mixed> $settings
     * @return self
     */
    public function mergeSettings(array $settings): self
    {
        $this->settings = array_merge_recursive($this->settings, $settings);

        return $this;
    }

    /**
     * Set color palette.
     *
     * @param array<array{slug: string, name: string, color: string}> $colors
     * @return self
     */
    public function setColorPalette(array $colors): self
    {
        $this->settings['color']['palette'] = $colors;

        return $this;
    }

    /**
     * Set font families.
     *
     * @param array<array{fontFamily: string, name: string, slug: string}> $fontFamilies
     * @return self
     */
    public function setFontFamilies(array $fontFamilies): self
    {
        $this->settings['typography']['fontFamilies'] = $fontFamilies;

        return $this;
    }

    /**
     * Set font sizes.
     *
     * @param array<array{slug: string, name: string, size: string}> $fontSizes
     * @return self
     */
    public function setFontSizes(array $fontSizes): self
    {
        $this->settings['typography']['fontSizes'] = $fontSizes;

        return $this;
    }

    /**
     * Set spacing scale.
     *
     * @param array<array{slug: string, name: string, size: string}> $spacingSizes
     * @return self
     */
    public function setSpacingSizes(array $spacingSizes): self
    {
        $this->settings['spacing']['spacingSizes'] = $spacingSizes;

        return $this;
    }

    /**
     * Set theme.json styles.
     *
     * @param array<string, mixed> $styles
     * @return self
     */
    public function setStyles(array $styles): self
    {
        $this->styles = $styles;

        return $this;
    }

    /**
     * Add a custom template.
     *
     * @param string $name Template file name (without .html)
     * @param string $title Template title
     * @param string[] $postTypes Post types that can use this template
     * @return self
     */
    public function addCustomTemplate(string $name, string $title, array $postTypes = ['page', 'post']): self
    {
        $this->customTemplates[] = [
            'name' => $name,
            'title' => $title,
            'postTypes' => $postTypes,
        ];

        return $this;
    }

    /**
     * Add a template part.
     *
     * @param string $name Template part file name (without .html)
     * @param string $title Template part title
     * @param string $area Template part area (header, footer, sidebar, etc.)
     * @return self
     */
    public function addTemplatePart(string $name, string $title, string $area = 'uncategorized'): self
    {
        $this->templateParts[] = [
            'name' => $name,
            'title' => $title,
            'area' => $area,
        ];

        return $this;
    }

    /**
     * Add pattern slugs to include.
     *
     * @param list<string> $patterns Pattern slugs
     * @return self
     */
    public function setPatterns(array $patterns): self
    {
        $this->patterns = array_values($patterns);

        return $this;
    }

    /**
     * Generate the theme.json array.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $json = [
            '$schema' => 'https://schemas.wp.org/trunk/theme.json',
            'version' => $this->version,
        ];

        if (!empty($this->settings)) {
            $json['settings'] = $this->settings;
        }

        if (!empty($this->styles)) {
            $json['styles'] = $this->styles;
        }

        if (!empty($this->customTemplates)) {
            $json['customTemplates'] = $this->customTemplates;
        }

        if (!empty($this->templateParts)) {
            $json['templateParts'] = $this->templateParts;
        }

        if (!empty($this->patterns)) {
            $json['patterns'] = $this->patterns;
        }

        return $json;
    }

    /**
     * Write theme.json to a file.
     *
     * @param string $path File path
     * @return bool Success
     */
    public function write(string $path): bool
    {
        $json = $this->generate();
        $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            return false;
        }

        $result = file_put_contents($path, $content);

        return $result !== false;
    }

    /**
     * Load existing theme.json and merge with current configuration.
     *
     * @param string $path File path
     * @return self
     */
    public function loadAndMerge(string $path): self
    {
        if (!file_exists($path)) {
            return $this;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return $this;
        }

        $existing = json_decode($content, true);

        if (!is_array($existing)) {
            return $this;
        }

        if (isset($existing['settings'])) {
            $this->settings = array_merge_recursive($existing['settings'], $this->settings);
        }

        if (isset($existing['styles'])) {
            $this->styles = array_merge_recursive($existing['styles'], $this->styles);
        }

        return $this;
    }
}
