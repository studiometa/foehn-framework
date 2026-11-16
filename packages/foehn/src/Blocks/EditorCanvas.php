<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use WP_REST_Request;

/**
 * The block editor canvas render of a Foehn block.
 *
 * The editor draws a Foehn block from its real Twig output, fetched from the
 * `block-renderer` REST endpoint with the `foehn_canvas` parameter. That render
 * differs from the front end in two places only, both of which the editor
 * script replaces with live editor components:
 *
 * - a container receives a marker as `$content`, where the editor mounts the
 *   inner blocks;
 * - `block_editable('<attribute>')` prints a marker attribute, on the element
 *   the editor turns into in-place rich text.
 *
 * Only the block the request asked for is in canvas mode. A Foehn block a
 * template renders inside it renders as it would on the front end.
 */
final class EditorCanvas
{
    /** Query parameter the editor script adds to its `block-renderer` requests. */
    public const string PARAM = 'foehn_canvas';

    /** Placeholder for the inner blocks, replaced by the editor with InnerBlocks. */
    public const string INNER_BLOCKS = '<foehn-inner-blocks></foehn-inner-blocks>';

    /** HTML attribute marking an element as the in-place editor of a block attribute. */
    public const string EDITABLE_ATTRIBUTE = 'data-foehn-editable';

    private static bool $requested = false;

    private static int $depth = 0;

    /**
     * Note whether the current REST request is a canvas render.
     *
     * Handler for the `rest_request_before_callbacks` filter. It returns the
     * response untouched: the filter is only a reliable place to read the
     * route and the parameters of the request being dispatched.
     */
    public static function capture(mixed $response, mixed $handler, WP_REST_Request $request): mixed
    {
        self::$requested =
            str_starts_with($request->get_route(), '/wp/v2/block-renderer/')
            && filter_var($request->get_param(self::PARAM), FILTER_VALIDATE_BOOLEAN);

        return $response;
    }

    /**
     * Run a block render, in canvas mode when it is the block the editor asked for.
     *
     * @template T
     * @param callable(bool): T $render Receives whether this render is the canvas render.
     * @return T
     */
    public static function render(callable $render): mixed
    {
        self::$depth++;

        try {
            return $render(self::isActive());
        } finally {
            self::$depth--;
        }
    }

    /**
     * Whether the block being rendered now is drawn on the editor canvas.
     */
    public static function isActive(): bool
    {
        return self::$requested && self::$depth === 1;
    }

    /**
     * Forget the captured request. For tests, and for long-running processes.
     */
    public static function reset(): void
    {
        self::$requested = false;
        self::$depth = 0;
    }
}
