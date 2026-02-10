<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Cleanup\DisableEmoji;
use Studiometa\Foehn\Hooks\Security\DisableXmlRpc;
use Studiometa\Foehn\Hooks\YouTubeNoCookieHooks;

return new FoehnConfig(
    discoveryCache: 'full',
    hooks: [
        CleanHeadTags::class,
        DisableEmoji::class,
        DisableXmlRpc::class,
        YouTubeNoCookieHooks::class,
    ],
);
