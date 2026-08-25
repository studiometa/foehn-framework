<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Contracts\ImageTransformer;
use WP_HTML_Tag_Processor;

/**
 * Sizes the images inside rendered content with the configured transformer,
 * rather than with the files WordPress cut at upload time.
 *
 * A template asks for a size and gets one. An image pasted into the editor does
 * not: WordPress writes whatever intermediate sizes were registered the day it
 * was uploaded, lists them in the attachment's metadata, and builds `srcset`
 * from that list forever after. Which is a problem in three ways, and the third
 * is the one that bites.
 *
 * The sizes are decided years before the design that displays them. Registering
 * a new one does nothing for images already in the library. And the metadata is
 * a *claim*, not a fact — it survives the files it names. Delete them, migrate
 * without them, change the upload directory, and `srcset` goes on advertising
 * widths that 404, which no page ever reports and no editor can see.
 *
 * A transformer answers from the original instead, at any width, on demand. So
 * every candidate here is derived from the original: nothing is claimed that
 * cannot be produced, and the intermediate files stop mattering — a site can
 * stop generating them entirely.
 *
 * Opt-in, in `foehn.config.php`, and inert with no transformer configured:
 * `NullTransformer` returns the URL it was given, so every candidate would be
 * the same image and this leaves the tag alone.
 *
 * ```php
 * hooks: [ContentImageHooks::class]
 * ```
 *
 * @see ImageCacheHooks for the other half — forgetting transforms when the
 *      original changes.
 */
final readonly class ContentImageHooks
{
    /**
     * The widths offered to the browser, before the original's own width caps
     * them.
     *
     * A ladder rather than a computed series: these are the widths a layout
     * actually resolves to across phones, tablets and desktops at one and two
     * times density, and they sit on the 100px grid `GlideConfig` snaps to, so
     * a request lands on a cache entry instead of beside one.
     */
    public const array WIDTHS = [400, 800, 1200, 1600, 2000, 2400];

    public function __construct(
        private ImageTransformer $transformer,
    ) {}

    /**
     * Rewrite one `<img>` from the rendered content.
     *
     * `wp_content_img_tag` and not `wp_calculate_image_srcset`, which is the
     * hook this looks like it wants: that one returns early when the metadata
     * lists no sizes, so on exactly the images this exists to repair — the ones
     * whose intermediate files are gone — it never runs at all.
     *
     * @param string $tag          The tag as WordPress has it, srcset included.
     * @param string $context      Which filter is asking. Unused; part of the signature.
     * @param int    $attachmentId 0 when the image is not in the library.
     */
    #[AsFilter('wp_content_img_tag', acceptedArgs: 3)]
    public function rewrite(string $tag, string $context, int $attachmentId): string
    {
        $attributes = $this->attributes($attachmentId);

        if ($attributes === null) {
            return $tag;
        }

        return $this->apply($tag, $attributes);
    }

    /**
     * What one attachment should be served as, or null to leave it alone.
     *
     * Public because it is the whole of the decision, and a template that
     * builds its own `<img>` wants the same answer this filter does — without
     * having to own a ladder of its own.
     *
     * @return array{src: string, srcset: string, sizes: string}|null
     */
    public function attributes(int $attachmentId): ?array
    {
        if ($attachmentId <= 0) {
            // Not an attachment: nothing names an original, so there is nothing
            // to derive a size from. An image hotlinked into the editor stays as
            // it was written.
            return null;
        }

        $source = wp_get_attachment_url($attachmentId);

        if (!is_string($source) || $source === '') {
            return null;
        }

        $widths = $this->widths($attachmentId);
        $candidates = [];

        foreach ($widths as $width) {
            $url = $this->transformer->url($source, ['w' => $width]);

            // A transformer that will not produce this size hands back the URL
            // it was given. Advertising that as `{$width}w` would describe the
            // original as something it is not, so the candidate is dropped —
            // which is also what makes this inert under `NullTransformer`.
            if ($url === $source) {
                continue;
            }

            $candidates[$width] = $url;
        }

        if ($candidates === []) {
            return null;
        }

        $widest = max(array_keys($candidates));
        $srcset = [];

        foreach ($candidates as $width => $url) {
            $srcset[] = $url . ' ' . $width . 'w';
        }

        return [
            // `src` is what a browser without `srcset` support uses, and what
            // every crawler and mail client reads. It is also the attribute that
            // carries the fault this class exists for — WordPress points it at
            // an intermediate file, which is the file that may be missing.
            // Pointing it at a transform of the original is what makes a broken
            // image whole.
            'src' => $candidates[$widest],
            'srcset' => implode(', ', $srcset),
            'sizes' => sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $widest),
        ];
    }

    /**
     * The widths worth offering for one attachment.
     *
     * Never past the original: an upscale is bytes spent on pixels that were
     * never photographed. An image smaller than every rung of the ladder keeps
     * its own width as the only candidate, so a 320px logo is still served
     * through the transformer — as one format, one file.
     *
     * @return list<int>
     */
    private function widths(int $attachmentId): array
    {
        $metadata = wp_get_attachment_metadata($attachmentId);

        // The stub types this as an array that always carries a width. Neither
        // half holds: the function returns `false` for an attachment with no
        // metadata, and the array is missing `width` for anything that is not an
        // image. Both are ordinary, and both are why this is guarded.
        //
        // @mago-expect analysis:redundant-null-coalesce
        $natural = is_array($metadata) ? (int) ($metadata['width'] ?? 0) : 0;

        if ($natural <= 0) {
            // No metadata at all — which happens, and is not a reason to refuse
            // to size the image. The ladder stands, and the transformer's own
            // bounds are what stop it.
            return self::WIDTHS;
        }

        $widths = array_values(array_filter(self::WIDTHS, static fn(int $width): bool => $width <= $natural));

        return $widths === [] ? [$natural] : $widths;
    }

    /**
     * Write the attributes onto the tag.
     *
     * `WP_HTML_Tag_Processor` and not a regular expression: `srcset` holds
     * commas and URLs hold everything, and an attribute rewritten by pattern is
     * how a stray quote in a filename becomes broken markup on a page nobody
     * looks at.
     *
     * @param array{src: string, srcset: string, sizes: string} $attributes
     */
    private function apply(string $tag, array $attributes): string
    {
        $processor = new WP_HTML_Tag_Processor($tag);

        if (!$processor->next_tag(['tag_name' => 'img'])) {
            return $tag;
        }

        $processor->set_attribute('src', $attributes['src']);
        $processor->set_attribute('srcset', $attributes['srcset']);

        // Only when WordPress has not already worked one out. It computes
        // `sizes` from the tag's own width and the theme's filters, and knows
        // more about the layout than this does.
        if (!is_string($processor->get_attribute('sizes'))) {
            $processor->set_attribute('sizes', $attributes['sizes']);
        }

        return $processor->get_updated_html();
    }
}
