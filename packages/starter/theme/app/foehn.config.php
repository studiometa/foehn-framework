<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
use Studiometa\Foehn\Hooks\Cleanup\DisableEmoji;
use Studiometa\Foehn\Hooks\Cleanup\DisableGlobalStyles;
use Studiometa\Foehn\Hooks\Cleanup\DisableOembed;
use Studiometa\Foehn\Hooks\Security\DisableVersionDisclosure;
use Studiometa\Foehn\Hooks\Security\DisableXmlRpc;
use Studiometa\Foehn\Hooks\Security\GenericLoginErrors;
use Studiometa\Foehn\Hooks\StudiometaUi;
use Studiometa\Foehn\Hooks\YouTubeNoCookieHooks;
use Tempest\Discovery\DiscoveryCacheStrategy;

return new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::FULL, hooks: [
    CleanHeadTags::class,
    DisableEmoji::class,
    // WordPress prints the styles it derives from `theme.json` inline, and
    // *after* the theme stylesheet. This starter ships no `theme.json`, so those
    // are core's defaults, and they win on load order — `body { padding: 0 }`
    // flattens a gutter the theme sets, with nothing in the theme's own CSS to
    // explain it. Remove this line if you adopt `theme.json` presets.
    DisableGlobalStyles::class,
    DisableOembed::class,
    DisableVersionDisclosure::class,
    DisableXmlRpc::class,
    GenericLoginErrors::class,
    // Registers the @ui and @svg Twig namespaces studiometa/ui ships its
    // components under. Inert when the package is not installed.
    StudiometaUi::class,
    YouTubeNoCookieHooks::class,
]);
