### Added

- The block editor draws a Føhn block from its own Twig template and makes it editable in place: the template's root element is the block element, the inner blocks of a container mount where the template prints `content`, and an element marked with `{{ block_editable('<attribute>') }}` is edited on the canvas. `is_block_editor()` tells a template it renders for the editor.
- A `richtext` control for a string attribute that keeps bold, italic and links as HTML, and a `placeholder` key for an empty in-place field.
- An `image` attribute gets a replace button in the block toolbar.
- The starter and the demo load the theme stylesheet and script in the block editor, on `enqueue_block_assets`, so blocks with js-toolkit components work on the canvas too.

### Changed

- The editor no longer shows a container's inner blocks without its template, and no longer shows a non-container block as an inert `ServerSideRender` preview. To keep editing a string attribute in the sidebar, do not mark it with `block_editable()`.
- The editor script depends on `wp-api-fetch` and `wp-data` instead of `wp-server-side-render`.
