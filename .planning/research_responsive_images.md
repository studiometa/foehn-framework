# Research: Smart Responsive Image Generation

Date: 2026-08-19. Question: should Føhn adopt [`tempest/responsive-image`](https://tempestphp.com/3.x/packages/responsive-image.md) to build a responsive image tool?

## Verdict

**Do not adopt the package. Build the feature.**

The package is a 7-file utility written for flat-file Tempest sites. Its data model (a source directory, a public directory, files identified by relative path) does not match WordPress, where images are attachments with an ID, database metadata, an alt field, generated sub-sizes, and a delete lifecycle. Re-shaping it to fit would mean bypassing everything it does.

The feature itself is worth building, because Føhn already owns the two parts the package lacks: an attribute + discovery system to declare presets in code, and Timber's `ImageHelper`, which already does on-demand generation with file-level caching and attachment cleanup. What is missing is the layer above: named presets, modern formats, and `<picture>` markup.

## Package audit

Facts read from the source at `tempestphp/responsive-image@main` (7 files: `Image`, `ResponsiveImageConfig`, `ResponsiveImageFactory`, `ScaleImage`, `Size`, `SrcSet`, `Exceptions`).

### Maturity

| Signal         | Value                                 |
| -------------- | ------------------------------------- |
| First commit   | 2026-05-27                            |
| Last push      | 2026-07-13                            |
| Stars          | 1                                     |
| Open issues    | 0                                     |
| Downloads      | 469 total / 138 per month             |
| Latest version | 1.0.1                                 |
| Requires       | `php ^8.5`, `intervention/image ^4.1` |
| License        | MIT                                   |

A three-month-old single-author utility with no external users. Not a dependency to put under client sites.

### Blocking technical findings

1. **It decodes the full image on every render, cache or no cache.** `create()` calls `getVariations()` and `getWidthAndHeight()` unconditionally, and each one calls `imageManager->decodePath()` — a full decode into memory. The `cache` flag is checked _after_ that, and only skips the write. A page with 20 images pays 40 full image decodes per request. Timber, by comparison, pays two `stat()` calls per variant.

2. **The width ladder is a byte-budget heuristic, and its first rung is the original.** `getVariations()` computes `pixelPrice = filesize / area`, then steps the byte budget down by 30% of the original size until it reaches zero — about four variants. The first iteration solves for the full budget, so it emits a variant at the original dimensions, i.e. a re-encoded duplicate of the source. The heuristic also assumes bytes scale linearly with pixel count, which is false for JPEG and worse for WebP/AVIF.

3. **No modern formats.** No WebP, no AVIF, no `<picture>`, no `type` negotiation. Only same-format downscales. In 2026 this is the largest share of the available byte savings, and it is the part WordPress core still does not do either.

4. **No art direction, no density descriptors, no LQIP, no `fetchpriority`, no `decoding`.**

5. **The `sizes` output is non-conformant.** `Size::__toString()` only ever emits `(max-width: Npx) Mpx`. The spec requires the last entry in a `sizes` list to be an unconditioned source size. Browsers are lenient, but the generated markup is wrong.

6. **`srcPath` → `publicPath` copy is a hazard in WordPress.** Uploads already live in the public web root, so both paths point at the same directory and `copy($src, $src)` truncates its own source. With `cache: true` the early return hides it; with `cache: false` it does not.

7. **Async needs `tempest/command-bus`.** Føhn does not have it, and it needs a worker process. Føhn already has its own `JobDispatcher` backed by Action Scheduler. So the async path is unusable as shipped, leaving synchronous generation on the request that first renders the image.

8. **No attachment awareness.** No attachment ID, no `_wp_attachment_image_alt`, no `wp_get_attachment_metadata`, no `wp_calculate_image_srcset` filter chain, no cleanup when the attachment is deleted, no support for offloaded uploads (S3/WP Offload Media), no crop or focal point.

### Worth stealing

The byte-budget ladder idea is good, even if this implementation is crude: choosing widths so each rung is a roughly equal step in **bytes** rather than in pixels is a better default than a fixed `[400, 800, 1200, 1600]` list, because it adapts to the image content. Worth keeping as a possible generator for preset widths, computed once at upload time rather than per render.

## What we already have

### WordPress core (as of 2026)

- `wp_get_attachment_image()` emits `srcset`, `sizes`, `loading="lazy"`, `decoding="async"`, and `fetchpriority="high"` on the likely LCP image.
- `add_image_size()` sub-sizes, generated at upload, already wired into Føhn through `#[AsImageSize]` + `ImageSizeDiscovery`.
- `wp_calculate_image_srcset` / `wp_calculate_image_sizes` filters to correct the ladder and the `sizes` attribute.
- **Not** in core: WebP/AVIF sub-size generation. It is still the [Modern Image Formats plugin](https://wordpress.org/plugins/webp-uploads/) (ex WebP Uploads), which generates AVIF when the server supports it, otherwise WebP, with an optional original-format fallback. Better `sizes` values are also still a Performance Lab module ("enhanced responsive images"), not core.

Core weaknesses that a Føhn feature should fix: every size is generated at upload (slow uploads, a bloated `uploads/` directory, and a re-upload needed whenever a size changes), `sizes` defaults to a naive `100vw`, and there is no `<picture>`/art-direction path.

### Timber 2.5 (already a dependency)

`Timber\ImageHelper` gives us most of the generation engine for free:

- `resize($src, $w, $h, $crop)`, `retina_resize()`, `letterbox()`, `img_to_jpg()`, `img_to_webp($src, $quality)`; exposed in Twig as `|resize`, `|retina`, `|letterbox`, `|tojpg`, `|towebp`.
- Generation is **on demand and cached by file**: `_operate()` builds the destination path, returns the existing URL when the file is present and not older than the source, and only then runs the operation. Two `stat()` calls on a hit.
- It runs through `wp_get_image_editor()`, so it uses Imagick when available. No new dependency, and AVIF comes along for free where the server's Imagick/GD supports it (WP has handled `image/avif` since 6.5).
- It cleans up after itself: `delete_generated_files()` is hooked to attachment deletion, so variants do not outlive their source.
- Extension points: `timber/image/new_url`, `timber/image/new_path` (to move all variants into a dedicated directory), `timber/allow_fs_write` (to make generation read-only in production).

Gaps: no AVIF helper (only `img_to_webp`), no `<picture>` markup, no ladder/`sizes` concept, no ID-based API, no deferred generation.

### Føhn

`#[AsImageSize]` + `ImageSizeDiscovery`, `ImageData` DTO (`fromAttachmentId`), `Acf\Fragments\ResponsiveImageBuilder`, `CleanImageSizes`, `#[AsJob]` + Action Scheduler dispatcher, `#[AsTwigExtension]`, the config-file convention (`app/*.config.php`), and a code generator (`MakeImageSizeCommand`).

Everything needed to declare presets in code and render them in Twig. Only the image logic is missing.

## Proposed design

Three thin layers. Each is useful alone, which keeps the first shippable version small.

### Layer 1 — Presets declared in code

Widths, formats, and the `sizes` expression belong together, in one versioned place, not spread across `add_image_size()` calls and hand-written Twig.

```php
// app/Images/CardImage.php
#[AsImagePreset(
    widths: [400, 800, 1200],           // or widths: Ladder::bytes(steps: 4)
    ratio: '4/3',                        // optional crop
    sizes: '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
    formats: [Format::Avif, Format::Webp, Format::Original],
)]
final class CardImage {}
```

Discovery registers each preset in a registry (and, where a preset needs a hard crop, registers the matching `add_image_size()` so the crop is done once at upload). This mirrors `ImageSizeDiscovery` exactly, so there is nothing new to learn or to test in a new way.

### Layer 2 — A generation service over Timber

```php
$picture = $images->render(CardImage::class, $attachmentId, alt: '…', lazy: false);
```

- Source of truth for dimensions and alt: `wp_get_attachment_metadata()` and `_wp_attachment_image_alt` — no image decode per render, unlike the Tempest package.
- Variant URLs: `ImageHelper::resize()` / `img_to_webp()`, plus an AVIF operation guarded by `wp_image_editor_supports(['mime_type' => 'image/avif'])`, with WebP then the original as fallbacks. Redirect variants into `uploads/foehn/` with `timber/image/new_path` so they are trivially purgeable and never confused with core sub-sizes.
- Cold-cache cost: the first render of a page generates the missing variants. Mitigate with `#[AsJob]` on `wp_generate_attachment_metadata` (pre-generate on upload) and a `wp foehn images:generate` backfill command. With the page cache from the sibling research doc in place, only cache misses ever pay this cost, which removes most of the pressure here.
- Set `timber/allow_fs_write` to false in production once generation is pre-warmed, so a request can never block on image work.

### Layer 3 — Markup

A Twig function/component emitting `<picture>` with one `<source type="image/avif">`, one `type="image/webp"`, and an `<img>` fallback carrying `srcset`, the preset's `sizes`, intrinsic `width`/`height` (no CLS), `loading`, `decoding`, and `fetchpriority` for the hero.

```twig
{{ picture('CardImage', post.thumbnail_id, alt: post.title) }}
```

`ResponsiveImageBuilder` (ACF) gets a `preset:` argument so a field group declares which preset its image field renders with.

### Phasing

1. Presets + registry + `<picture>` on top of WP sub-sizes and Timber `resize`. Ships value on its own.
2. AVIF/WebP variants with capability detection and graceful fallback.
3. Pre-generation job, backfill CLI command, `allow_fs_write` off in production.
4. Optional: byte-budget ladder generator, LQIP/blur placeholder, art direction (per-breakpoint sources).

Rough effort: layer 1+3 is a 2–3 day job with tests; layer 2 with formats and jobs, another 2–3 days. Small, because nothing here is new plumbing.

## Alternatives considered

| Option                                                         | Verdict                                                                                                                                                                                                            |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `tempest/responsive-image`                                     | **No.** Immature, decodes per render, no modern formats, wrong data model for WP.                                                                                                                                  |
| `league/glide` or `intervention/image` directly, own pipeline  | Possible, but adds a dependency to do what `wp_get_image_editor()` + Timber already do. Only justified if we need on-the-fly URL-driven transforms.                                                                |
| Modern Image Formats plugin (`webp-uploads`)                   | **Recommend as the fallback today** and as a reference. Solves formats with zero code; does not solve presets, `sizes`, or `<picture>`. Plugin dependency.                                                         |
| CDN image resizing (Cloudflare Images, Bunny Optimizer, imgix) | Near-zero code, best results, but a paid external dependency and per-project setup. Keep as a documented escape hatch: layer 3 should be able to emit CDN URLs instead of local variants behind one config switch. |

## Risks

- Variant explosion on disk: presets × widths × formats. Keep preset count low, put variants in `uploads/foehn/` so they can be dropped and rebuilt, and document the backfill command.
- Imagick availability varies per host; AVIF encoding is CPU-heavy. Capability detection and pre-generation are both required, not optional.
- Offloaded uploads (S3) break local file generation. Detect and fall back to core sub-sizes or CDN URLs.
