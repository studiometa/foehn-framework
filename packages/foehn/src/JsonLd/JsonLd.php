<?php

declare(strict_types=1);

namespace Studiometa\Foehn\JsonLd;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Schema.org JSON-LD nodes that blocks add while they render.
 *
 * A block, such as a FAQ block, calls `JsonLd::add($node)` when it renders. Rank Math
 * prints its graph in `<head>`, before the blocks render, so Føhn prints these nodes
 * in a second `<script type="application/ld+json">` on `wp_footer`.
 *
 * The nodes of a page, which are known before the page renders, come from
 * #[AsJsonLd] classes instead.
 */
final class JsonLd
{
    /** The class of the scripts Føhn prints. */
    public const string SCRIPT_CLASS = 'foehn-json-ld';

    /**
     * The nodes added during this request.
     *
     * Static, because a block renders without access to the container. There is one
     * page per PHP request.
     *
     * @var list<array<array-key, mixed>>
     */
    private static array $nodes = [];

    /**
     * Add a node to the graph Føhn prints in the footer.
     *
     * @param array<array-key, mixed>|object $node An array, a JsonSerializable object, or an object with `toArray()`
     */
    public static function add(array|object $node): void
    {
        self::$nodes[] = self::normalize($node);
    }

    /**
     * The nodes added since the last call, and empty the list.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function flush(): array
    {
        $nodes = self::$nodes;
        self::$nodes = [];

        return $nodes;
    }

    /**
     * A node as an array, without the `@context` that a graph holds once.
     *
     * @param array<array-key, mixed>|object $node
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException When the node cannot be turned into an array
     */
    public static function normalize(array|object $node): array
    {
        $array = match (true) {
            is_array($node) => $node,
            $node instanceof JsonSerializable => $node->jsonSerialize(),
            method_exists($node, 'toArray') => $node->toArray(),
            default => null,
        };

        if (!is_array($array)) {
            throw new InvalidArgumentException(sprintf(
                'A JSON-LD node must be an array, a JsonSerializable object that serializes to an array, or an object with toArray(); got %s.',
                get_debug_type($node),
            ));
        }

        unset($array['@context']);

        return $array;
    }

    /**
     * The `<script type="application/ld+json">` of a graph, or an empty string when
     * there is no node.
     *
     * `JSON_HEX_TAG` escapes `<` and `>`, so a value that holds `</script>` cannot
     * close the script.
     *
     * @param list<array<array-key, mixed>> $nodes
     */
    public static function script(array $nodes): string
    {
        if ($nodes === []) {
            return '';
        }

        $json = wp_json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG,
        );

        if ($json === false) {
            return '';
        }

        return sprintf("<script type=\"application/ld+json\" class=\"%s\">%s</script>\n", self::SCRIPT_CLASS, $json);
    }
}
