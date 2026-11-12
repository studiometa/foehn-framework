<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanContent;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Cleanup\DisableEmoji;
use Studiometa\Foehn\Hooks\Cleanup\DisableGlobalStyles;
use Studiometa\Foehn\Hooks\Cleanup\DisableOembed;
use Studiometa\Foehn\Hooks\QueryFiltersHook;
use Studiometa\Foehn\Hooks\S3UploadsEndpoint;
use Studiometa\Foehn\Hooks\Security\DisableFileEditor;
use Studiometa\Foehn\Hooks\Security\DisableVersionDisclosure;
use Studiometa\Foehn\Hooks\Security\DisableXmlRpc;
use Studiometa\Foehn\Hooks\Security\GenericLoginErrors;
use Studiometa\Foehn\Hooks\Security\RestApiAuth;
use Studiometa\Foehn\Hooks\Security\SecurityHeaders;
use Studiometa\Foehn\Hooks\StudiometaUi;
use Studiometa\Foehn\Hooks\YouTubeNoCookieHooks;
use Studiometa\Foehn\Images\ContentImageHooks;
use Studiometa\Foehn\Images\GlideTransformer;
use Studiometa\Foehn\Images\ImageCacheHooks;
use Tempest\Discovery\DiscoveryCacheStrategy;

return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    imageTransformer: GlideTransformer::class,
    hooks: [
        // Removes empty paragraphs from content, and the "Category:" and "Series:"
        // prefixes from archive titles: the archive template prints the name alone.
        CleanContent::class,
        CleanHeadTags::class,
        DisableEmoji::class,
        // The theme ships no theme.json, so the inline global styles WordPress prints
        // from its defaults style nothing of ours.
        DisableGlobalStyles::class,
        DisableOembed::class,
        // DisableFeeds is not here: the journal is a blog, and its feed at /feed/ is
        // worth keeping. The theme declares no `automatic-feed-links`, so there is no
        // head link for the hook to remove anyway.
        DisableFileEditor::class,
        DisableVersionDisclosure::class,
        DisableXmlRpc::class,
        GenericLoginErrors::class,
        // Removes /wp/v2/users for visitors, which would list the admin login. The
        // other routes stay public, the demo's own /demo/v1/projects included.
        RestApiAuth::class,
        // Sent by PHP. A page that nginx serves from the page cache does not reach PHP,
        // so page-cache.config.php repeats them in `responseHeaders`.
        SecurityHeaders::class,
        // A transform is keyed on the path and the parameters, never on the pixels, so
        // a crop in the media library would go on serving the old ones forever. This
        // forgets an image's transforms when the image itself changes.
        ImageCacheHooks::class,
        // An image an editor puts in content gets its srcset from the transformer, at
        // the widths a layout asks for, rather than from the sizes WordPress cut at
        // upload. See the journal posts.
        ContentImageHooks::class,
        // Registers the @ui and @svg Twig namespaces studiometa/ui ships its
        // components under. Inert when the package is not installed.
        StudiometaUi::class,
        // Makes `posts_per_page` settable from the URL, bounded by the allowlist in
        // `query-filters.config.php`. Everything else the projects archive filters by
        // is a public query var WordPress already reads, so this hook is here for the
        // one filter that is not.
        QueryFiltersHook::class,
        // Points humanmade/s3-uploads at RustFS. Without it the plugin talks to AWS, whose
        // endpoint is the only one its constants can describe.
        S3UploadsEndpoint::class,
        YouTubeNoCookieHooks::class,
    ],
);
