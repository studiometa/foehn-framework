<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Cleanup\DisableEmoji;
use Studiometa\Foehn\Hooks\Cleanup\DisableOembed;
use Studiometa\Foehn\Hooks\QueryFiltersHook;
use Studiometa\Foehn\Hooks\S3UploadsEndpoint;
use Studiometa\Foehn\Hooks\Security\DisableVersionDisclosure;
use Studiometa\Foehn\Hooks\Security\DisableXmlRpc;
use Studiometa\Foehn\Hooks\Security\GenericLoginErrors;
use Studiometa\Foehn\Hooks\StudiometaUi;
use Studiometa\Foehn\Hooks\YouTubeNoCookieHooks;
use Studiometa\Foehn\Images\GlideTransformer;
use Studiometa\Foehn\Images\ImageCacheHooks;
use Tempest\Discovery\DiscoveryCacheStrategy;

return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    imageTransformer: GlideTransformer::class,
    hooks: [
        CleanHeadTags::class,
        DisableEmoji::class,
        DisableOembed::class,
        DisableVersionDisclosure::class,
        DisableXmlRpc::class,
        GenericLoginErrors::class,
        // A transform is keyed on the path and the parameters, never on the pixels, so
        // a crop in the media library would go on serving the old ones forever. This
        // forgets an image's transforms when the image itself changes.
        ImageCacheHooks::class,
        // Registers the @ui and @svg Twig namespaces studiometa/ui ships its
        // components under. Inert when the package is not installed.
        StudiometaUi::class,
        // Makes `posts_per_page` settable from the URL, bounded by the allowlist in
        // `query-filters.config.php`. Everything else the projects archive filters by
        // is a public query var WordPress already reads, so this hook is here for the
        // one filter that is not.
        QueryFiltersHook::class,
        // Points humanmade/s3-uploads at MinIO. Without it the plugin talks to AWS, whose
        // endpoint is the only one its constants can describe.
        S3UploadsEndpoint::class,
        YouTubeNoCookieHooks::class,
    ],
);
