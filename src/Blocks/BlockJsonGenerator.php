<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Blocks;

use Studiometa\WPTempest\Attributes\AsBlock;
use Studiometa\WPTempest\Contracts\BlockInterface;

/**
 * Generates block.json configuration for native Gutenberg blocks.
 */
final class BlockJsonGenerator
{
    /**
     * Generate block.json array from attribute and class.
     *
     * @param AsBlock $attribute
     * @param class-string<BlockInterface> $className
     * @return array<string, mixed>
     */
    public function generate(AsBlock $attribute, string $className): array
    {
        $json = [
            '$schema' => 'https://schemas.wp.org/trunk/block.json',
            'apiVersion' => 3,
            'name' => $attribute->name,
            'title' => $attribute->title,
            'category' => $attribute->category,
            'textdomain' => $this->getTextDomain($attribute->name),
        ];

        // Optional fields
        if ($attribute->icon !== null) {
            $json['icon'] = $attribute->icon;
        }

        if ($attribute->description !== null) {
            $json['description'] = $attribute->description;
        }

        if (!empty($attribute->keywords)) {
            $json['keywords'] = $attribute->keywords;
        }

        if (!empty($attribute->supports)) {
            $json['supports'] = $attribute->supports;
        }

        if ($attribute->parent !== null) {
            $json['parent'] = [$attribute->parent];
        }

        if (!empty($attribute->ancestor)) {
            $json['ancestor'] = $attribute->ancestor;
        }

        // Attributes from class
        if (method_exists($className, 'attributes')) {
            $json['attributes'] = $className::attributes();
        }

        // Interactivity support
        if ($attribute->interactivity) {
            $json['supports'] = array_merge($json['supports'] ?? [], [
                'interactivity' => true,
            ]);
        }

        // Assets
        if ($attribute->editorScript !== null) {
            $json['editorScript'] = $attribute->editorScript;
        }

        if ($attribute->editorStyle !== null) {
            $json['editorStyle'] = $attribute->editorStyle;
        }

        if ($attribute->style !== null) {
            $json['style'] = $attribute->style;
        }

        if ($attribute->viewScript !== null) {
            $json['viewScript'] = $attribute->viewScript;
        }

        return $json;
    }

    /**
     * Extract text domain from block name.
     *
     * @param string $name Block name (e.g., 'theme/counter')
     * @return string Text domain
     */
    private function getTextDomain(string $name): string
    {
        $parts = explode('/', $name);

        return $parts[0] ?? 'theme';
    }

    /**
     * Write block.json to a file.
     *
     * @param array<string, mixed> $json
     * @param string $path File path
     * @return bool Success
     */
    public function write(array $json, string $path): bool
    {
        $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            return false;
        }

        $result = file_put_contents($path, $content);

        return $result !== false;
    }
}
