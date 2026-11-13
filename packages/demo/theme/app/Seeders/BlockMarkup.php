<?php

declare(strict_types=1);

namespace Demo\Seeders;

/**
 * Block markup for the seeders, as the editor saves it.
 *
 * A seeded page written as blocks is a page an editor can open and change, and it
 * shows the theme's blocks the way a visitor meets them.
 */
final class BlockMarkup
{
    /**
     * The comment delimiters around the saved HTML, or a self-closing comment for a
     * dynamic block that saves none. A null attribute is left out.
     *
     * @param array<string, mixed> $attributes
     */
    public static function block(string $name, array $attributes = [], string $html = ''): string
    {
        $attributes = array_filter($attributes, static fn(mixed $value): bool => $value !== null);
        $json = $attributes === []
            ? ''
            : ' ' . wp_json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($html === '') {
            return "<!-- wp:{$name}{$json} /-->";
        }

        return "<!-- wp:{$name}{$json} -->\n{$html}\n<!-- /wp:{$name} -->";
    }

    public static function paragraph(string $text): string
    {
        return self::block('paragraph', [], '<p>' . $text . '</p>');
    }
}
