### Fixed

- A page that a template controller renders now goes through the template enhancement output buffer of WordPress 6.9 and later. Føhn printed the page inside the `template_include` filter and returned an empty string, so `wp_before_include_template` never fired, the buffer never started, and the styles of the blocks printed late (`wp-block-*-inline-css`, `core-block-supports-inline-css`) stayed at the end of the body. `template_include` now returns the path of a Føhn template file, `resources/templates/template-controller.php`, and WordPress includes it: that file runs the controller

### Changed

- **Breaking:** a `template_include` callback with a priority higher than 5 now receives the path of the Føhn template file, not an empty string, for a request that a template controller handles. A callback that returns another file replaces the controller. To keep a request away from the controllers, use the `foehn/template_controller/skip` filter
