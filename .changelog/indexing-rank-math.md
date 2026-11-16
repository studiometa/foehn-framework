### Fixed

- Outside production, a page now carries `noindex, nofollow` in its robots meta tag when Rank Math is active. Rank Math removes every `wp_robots` filter and printed `index, follow`, so a staging page was indexable from its HTML (the `X-Robots-Tag` header still said `noindex`). Føhn now also filters `rank_math/frontend/robots`. Yoast SEO needed no change
