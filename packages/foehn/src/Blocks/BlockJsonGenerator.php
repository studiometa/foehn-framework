<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\Contracts\BlockInterface;

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
            // Every Foehn block is dynamic, so "Edit as HTML" can only ever invalidate it.
            // Mirrors the seed in BlockDiscovery::doRegisterBlock(): an author who sets
            // `html` explicitly still wins.
            'supports' => $attribute->supports + ['html' => false],
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

        if ($attribute->parent !== null) {
            $json['parent'] = [$attribute->parent];
        }

        if (!empty($attribute->ancestor)) {
            $json['ancestor'] = $attribute->ancestor;
        }

        // Inner blocks: only allowedBlocks is a block.json property.
        // The template and the template lock travel in the editor payload.
        if (!empty($attribute->allowedBlocks)) {
            $json['allowedBlocks'] = $attribute->allowedBlocks;
        }

        // Attributes from class, without the editor-only keys
        if (method_exists($className, 'attributes')) {
            $json['attributes'] = BlockAttributeSchema::toRegistration($className::attributes());
        }

        // Interactivity support
        if ($attribute->interactivity) {
            $json['supports']['interactivity'] = true;
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
        // explode() always yields at least one element, so the namespace is the first one.
        return explode('/', $name)[0];
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
