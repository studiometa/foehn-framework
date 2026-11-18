# studiometa/foehn-places

Place locator for [Føhn](https://github.com/studiometa/foehn-framework): a `place` post type with native fields, an address geocoder in the editor, spatial search and facets.

See the [documentation](https://studiometa.github.io/foehn-framework/guide/places).

> **Note**
> This package is part of the [Føhn Framework](https://github.com/studiometa/foehn-framework) monorepo.
> Please report issues and submit pull requests in the [main repository](https://github.com/studiometa/foehn-framework).

## Installation

```bash
composer require studiometa/foehn-places
```

`studiometa/foehn-places` requires the exact same version of `studiometa/foehn`. Upgrade the two packages together, for example `composer require studiometa/foehn:<version> studiometa/foehn-places:<version>`.

Add `Studiometa\Foehn\Places\Hooks\PlacesHooks` to `FoehnConfig::$hooks`, then flush the rewrite rules.
