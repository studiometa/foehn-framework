<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Core\DiscoveryCacheStrategy;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Cleanup\DisableEmoji;
use Studiometa\Foehn\Hooks\Cleanup\DisableOembed;
use Studiometa\Foehn\Hooks\Security\DisableVersionDisclosure;
use Studiometa\Foehn\Hooks\Security\DisableXmlRpc;
use Studiometa\Foehn\Hooks\Security\GenericLoginErrors;
use Studiometa\Foehn\Hooks\YouTubeNoCookieHooks;

return new FoehnConfig(
    discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
    hooks: [
        CleanHeadTags::class,
        DisableEmoji::class,
        DisableOembed::class,
        DisableVersionDisclosure::class,
        DisableXmlRpc::class,
        GenericLoginErrors::class,
        YouTubeNoCookieHooks::class,
    ],
);
