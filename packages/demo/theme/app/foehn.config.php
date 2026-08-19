<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Cleanup\DisableEmoji;
use Studiometa\Foehn\Hooks\Cleanup\DisableOembed;
use Studiometa\Foehn\Hooks\S3UploadsEndpoint;
use Studiometa\Foehn\Hooks\Security\DisableVersionDisclosure;
use Studiometa\Foehn\Hooks\Security\DisableXmlRpc;
use Studiometa\Foehn\Hooks\Security\GenericLoginErrors;
use Studiometa\Foehn\Hooks\YouTubeNoCookieHooks;
use Tempest\Discovery\DiscoveryCacheStrategy;

return new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::FULL, hooks: [
    CleanHeadTags::class,
    DisableEmoji::class,
    DisableOembed::class,
    DisableVersionDisclosure::class,
    DisableXmlRpc::class,
    GenericLoginErrors::class,
    // Points humanmade/s3-uploads at MinIO. Without it the plugin talks to AWS, whose
    // endpoint is the only one its constants can describe.
    S3UploadsEndpoint::class,
    YouTubeNoCookieHooks::class,
]);
