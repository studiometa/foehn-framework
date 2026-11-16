### Fixed

- Template controllers take the page template that a page or a post selects (`_wp_page_template`) into account. Its file name without `.php`, such as `page-wide` for `page-wide.php`, is the first template name of the request, before `page-{slug}`, `page-{id}` and `page`, as in the WordPress hierarchy. A controller can claim it with `#[AsTemplateController('page-wide')]`. A controller that claims `page-*` now also gets the page templates whose file starts with `page-`

### Changed

- **Breaking:** `TemplateNames::single()` and `TemplateNames::page()` take a third argument, the page template of the post (`get_page_template_slug()`), or `null`
