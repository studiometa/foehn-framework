### Fixed

- The `wp_interactive`, `wp_context`, `wp_directive`, `wp_bind`, `wp_on`, `wp_class` and `wp_text` Twig functions and the `video_embed`, `video_id`, `video_platform` and `video_is_supported` functions and filters are registered. Their extensions had no `#[AsTwigExtension]`, so a template that used them failed with "Unknown function".
