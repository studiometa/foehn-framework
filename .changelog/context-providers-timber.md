### Fixed

- Context providers now run for a view that a template renders with Timber directly, such as `Timber::render('components/footer.twig', Timber::context())` in a `footer.php` that a plugin loads. The view name is the file name without `.twig`. Before, they ran only for a view rendered with `ViewEngine::render()`, so `current_year` and the other values of a `*` provider were missing. A plugin that renders its own Twig files with Timber now gets the `*` providers too
