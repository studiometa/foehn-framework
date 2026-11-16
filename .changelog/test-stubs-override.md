### Changed

- The test bootstrap of the starter loads `tests/php/stubs.php`, the stubs of the project, before `vendor/studiometa/foehn/tests/wp-stubs.php`. Each WordPress function in the Føhn stubs is defined only when it does not exist yet, so a stub of the project now wins, also after a Føhn release adds a stub with the same name. A project created from an earlier starter must load its own stubs before the Føhn file in `tests/php/bootstrap.php`. See [Unit tests](https://studiometa.github.io/foehn-framework/guide/starter-theme#unit-tests)
