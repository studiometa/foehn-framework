### Added

- `wp foehn inspect` starts its summary with a `problems` section, and `wp foehn inspect problems` gives the `class`, `attribute`, `reason` and `fix` of each one: the classes discovery skipped, and a post type or taxonomy that names a taxonomy or post type no attribute declares and WordPress does not register. See [Problems and Errors](https://studiometa.github.io/foehn-framework/guide/troubleshooting)
- `wp foehn discovery:status` lists the classes discovery skipped, with the reason and the fix

### Changed

- A class whose attribute contract is broken (`#[AsPostType]` on a class that does not extend `Timber\Post`, `#[AsBlock]` without `BlockInterface`, an invalid `#[AsPostMeta]`…) is skipped and recorded instead of stopping discovery with an exception, so every other class still registers. The records are kept in the discovery cache, and `debug` raises each one as a PHP warning instead of logging only the failures of opt-in hooks. A project that relied on the exception must now read `problems`
- A class that `#[AsCliCommand]`, `#[AsSeeder]`, `#[AsTwigExtension]`, `#[AsCron]` or `#[AsJob]` skipped, and a class in `hooks` that does not exist, are now recorded as problems instead of being skipped without a word
- `TimberViewEngine` throws `Studiometa\Foehn\Views\ViewRenderingFailed`, still a `RuntimeException`. A Twig error keeps its message, its template and its line, names the view that was asked for, and is the previous exception. A missing view names every view tried, the directories Timber searched and the file to create
- `TimberViewEngine::exists()` asks Timber's loader, so it finds a template exactly when rendering finds it
