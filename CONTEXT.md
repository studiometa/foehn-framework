# Domain language

The terms Føhn's own code uses, so that a name means one thing everywhere. Architecture vocabulary (module, interface, seam, depth) is separate — see the `codebase-design` skill.

## Discovery

**Attribute** — a `#[As…]` class in `src/Attributes/`. A `final readonly class` whose constructor promotes every parameter, so the constructor signature is the whole declaration. Nothing else in Føhn may assume an attribute's shape independently: the constructor is the single source of both its arguments and their defaults.

**Discovery** — a `WpDiscovery` implementation that finds one kind of attribute and registers it with WordPress. It has exactly two moments: `discover()`, which inspects one class, and `apply()`, which registers what was found, at a WordPress lifecycle phase the `DiscoveryRunner` chooses.

**Discovery item** — what `discover()` records: the attribute instance, plus the reflection facts that are not in the attribute (the class name, a method name, whether the class implements an interface). One shape, whether the item was just scanned or restored from the cache. Values derived from the attribute are computed in `apply()`, never stored.

**Attribute codec** — `AttributeCodec`, which turns an attribute instance into a plain array for the cache file and rebuilds it by name. It reflects the promoted constructor, so no discovery describes its own cache format.

**Discovery phase** — `early` (`after_setup_theme`), `main` (`init`) or `late` (`wp_loaded`). Which phase a discovery applies in is declared once, in `DiscoveryRunner::getDiscoveryPhases()`.

**Discovery location** — where a scanned class came from, as a PSR-4 namespace. Cached items stay grouped by location.

## Code generation

**Stub** — a class in `src/Console/Stubs/`, marked `#[SkipDiscovery]`, that a `make:` command copies. A stub is real, compilable code carrying real attributes, so it can be reflected and tested rather than only string-matched. Its own attribute arguments are the defaults a command may override.

**Generation request** — `GenerationRequest`: what a command wants generated, described but not performed. Attribute arguments are set by name; only genuine code fragments are substituted textually, and each substitution must find its target or the generation fails.

**Generated file** — `GeneratedFile`: a path and its contents, not yet written. Generating and writing are separate, so `--dry-run` is a command that never writes.

## Views

**Template context** — `TemplateContext`: the typed, immutable view of Timber's context array handed to a template controller. Timber uses `false` rather than `null` for an absent value, so every field is checked by type.

**Context provider** — a class that adds data to a `TemplateContext` for templates matching a pattern, applied by priority.

**Template controller** — a class that takes over rendering for one or more WordPress templates, returning the markup or `null` to let WordPress continue.

## Blocks

**Block** — a class with `#[AsBlock]` rendering a native Gutenberg block server-side. Every Føhn block is dynamic: there is no static save output.

**Block attribute schema** — what a block's `attributes()` returns. It serves two audiences, split by `BlockAttributeSchema`: the keys WordPress accepts at registration, and the keys the editor sidebar needs.

**Editor definition** — the payload `BlockDiscovery::getEditorDefinitions()` exposes as `window.foehnBlocks`, which drives the generic editor registrar so a block needs no per-block JavaScript.

**ACF block** — a class with `#[AsAcfBlock]`, registered through ACF rather than natively. A separate concept from a block; the two do not share a registration path.
