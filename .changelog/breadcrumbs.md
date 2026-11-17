### Added

- `breadcrumbs()`, a Twig function and a PHP helper (`Studiometa\Foehn\breadcrumbs()`), gives the breadcrumb trail of the current request as a list of `{label, url}` items, so the theme writes its own markup. The current page, the last item, has no URL. The items come from Rank Math. The list is empty when Rank Math or its breadcrumbs are off
