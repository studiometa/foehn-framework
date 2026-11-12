### Fixed

- The `wp_interactive`, `wp_context`, `wp_directive`, `wp_bind`, `wp_on`, `wp_class` and `wp_text` Twig functions and the `video_embed`, `video_id`, `video_platform` and `video_is_supported` functions and filters are registered. Their extensions had no `#[AsTwigExtension]`, so a template that used them failed with "Unknown function".
- `Model::query()->get()` and `Model::all()` return a list of posts. They returned Timber's collection object, so PHP threw a `TypeError` on the `array` return type at every call.
- `wp_context()` and the `wp_context` filter encode `<`, `>` and `&` like WordPress does. A `>` in a value made `wptexturize` rewrite the rest of a block rendered in post content, and the block broke.
- The view script of a block with `interactivity: true` depends on `@wordpress/interactivity`, so WordPress puts that module in the import map. Before, `import { store } from '@wordpress/interactivity'` failed on a page with no core interactive block.
