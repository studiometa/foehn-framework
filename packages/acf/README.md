# studiometa/foehn-acf

Advanced Custom Fields integration for [Føhn](https://github.com/studiometa/foehn-framework): ACF blocks, field groups and options pages, discovered from attributes.

See the [documentation](https://studiometa.github.io/foehn-framework/).

> **Note**
> This package is part of the [Føhn Framework](https://github.com/studiometa/foehn-framework) monorepo.
> Please report issues and submit pull requests in the [main repository](https://github.com/studiometa/foehn-framework).

## Installation

```bash
composer require studiometa/foehn-acf
```

ACF Pro is a WordPress plugin and is not a Composer dependency of this package. Nothing here registers anything when ACF is absent: each discovery guards on the function it needs.

## Why it is a package

Custom fields no longer require a paid plugin. `#[AsPostMeta]` in the framework registers a meta key with a REST schema, which is what puts a field in the block editor and makes it bindable — so the default path needs no ACF at all.

ACF remains the better answer when the editing UI matters: repeaters, flexible content, conditional logic and media pickers are its actual product. This package is how a project opts into it.

The two do not conflict at the storage layer. ACF stores its values in ordinary post meta, so declaring `#[AsPostMeta(key: 'price')]` for a key ACF also manages is legitimate and useful: ACF keeps the editing UI, and the declaration adds the REST schema. That is the migration path.

## What it provides

| Attribute             | Registers       |
| --------------------- | --------------- |
| `#[AsAcfBlock]`       | An ACF block    |
| `#[AsAcfFieldGroup]`  | A field group   |
| `#[AsAcfOptionsPage]` | An options page |

Plus `make:acf-block`, `make:field-group` and `make:options-page`, the four field fragment builders, and `AcfOptionsService`.

## Upgrading from Føhn 0.4

The classes keep their namespaces, so no imports change. Add the requirement:

```bash
composer require studiometa/foehn-acf
```

## License

MIT
