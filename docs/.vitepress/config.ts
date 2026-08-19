import { defineConfig } from "vitepress";
import llmstxt from "vitepress-plugin-llms";

export default defineConfig({
  vite: {
    plugins: [llmstxt()],
  },

  markdown: {
    toc: { level: [2, 3] },
  },

  title: "Foehn",
  titleTemplate: ":title — Foehn, modern WordPress development",
  description:
    "A modern WordPress framework powered by Tempest, featuring attribute-based auto-discovery",

  base: "/foehn-framework/",

  head: [["link", { rel: "icon", type: "image/svg+xml", href: "/foehn/favicon.svg" }]],

  themeConfig: {
    logo: "/logo.svg",

    nav: [
      { text: "Guide", link: "/guide/getting-started" },
      { text: "API Reference", link: "/api/" },
      {
        text: "GitHub",
        link: "https://github.com/studiometa/foehn-framework",
      },
    ],

    sidebar: {
      "/guide/": [
        {
          text: "Introduction",
          items: [
            { text: "Getting Started", link: "/guide/getting-started" },
            { text: "Starter Theme", link: "/guide/starter-theme" },
            { text: "Demo Project", link: "/guide/demo" },
            { text: "Manual Installation", link: "/guide/installation" },
          ],
        },
        {
          text: "Core Concepts",
          items: [
            { text: "Hooks", link: "/guide/hooks" },
            { text: "Assets", link: "/guide/assets" },
            { text: "Post Types", link: "/guide/post-types" },
            { text: "Querying Posts", link: "/guide/querying-posts" },
            { text: "Taxonomies", link: "/guide/taxonomies" },
            { text: "Menus", link: "/guide/menus" },
          ],
        },
        {
          text: "Views & Templates",
          items: [
            { text: "Context Providers", link: "/guide/context-providers" },
            { text: "Template Controllers", link: "/guide/template-controllers" },
            { text: "Twig Extensions", link: "/guide/twig-extensions" },
            { text: "Query Filters", link: "/guide/query-filters" },
          ],
        },
        {
          text: "Blocks",
          items: [
            { text: "Arrayable DTOs", link: "/guide/arrayable-dtos" },
            { text: "Native Blocks", link: "/guide/native-blocks" },
            { text: "Block Editor", link: "/guide/block-editor" },
            { text: "Block Patterns", link: "/guide/block-patterns" },
            { text: "Block Bindings", link: "/guide/block-bindings" },
          ],
        },
        {
          text: "ACF (optional package)",
          items: [
            { text: "ACF Blocks", link: "/guide/acf-blocks" },
            { text: "ACF Options Pages", link: "/guide/acf-options-pages" },
            { text: "Field Fragments", link: "/guide/field-fragments" },
          ],
        },
        {
          text: "Advanced",
          items: [
            { text: "REST API", link: "/guide/rest-api" },
            { text: "Rewrite Rules", link: "/guide/rewrite-rules" },
            { text: "Settings Pages", link: "/guide/settings-pages" },
            { text: "Render API", link: "/guide/render-api" },
            { text: "Uploads", link: "/guide/uploads" },
            { text: "Caching", link: "/guide/caching" },
            { text: "Page Cache", link: "/guide/page-cache" },
            { text: "Shortcodes", link: "/guide/shortcodes" },
            { text: "CLI Commands", link: "/guide/cli-commands" },
            { text: "Discovery Cache", link: "/guide/discovery-cache" },
            { text: "Custom Discovery", link: "/guide/custom-discovery" },
          ],
        },
        {
          text: "Best Practices",
          items: [
            { text: "Theme Conventions", link: "/guide/theme-conventions" },
            { text: "Security", link: "/guide/security" },
          ],
        },
        {
          text: "Migration",
          items: [
            { text: "To v0.2.0", link: "/guide/migration-0.2" },
            { text: "From wp-toolkit", link: "/guide/migration-wp-toolkit" },
          ],
        },
      ],
      "/api/": [
        {
          text: "Attributes",
          items: [
            { text: "Overview", link: "/api/" },
            { text: "#[AsAction]", link: "/api/as-action" },
            { text: "#[AsFilter]", link: "/api/as-filter" },
            { text: "#[AsPostType]", link: "/api/as-post-type" },
            { text: "#[AsPostMeta]", link: "/api/as-post-meta" },
            { text: "#[AsTaxonomy]", link: "/api/as-taxonomy" },
            { text: "#[AsMenu]", link: "/api/as-menu" },
            { text: "#[AsTimberModel]", link: "/api/as-timber-model" },
            { text: "#[AsContextProvider]", link: "/api/as-context-provider" },
            { text: "#[AsTemplateController]", link: "/api/as-template-controller" },
            { text: "#[AsBlock]", link: "/api/as-block" },
            { text: "#[AsAcfBlock]", link: "/api/as-acf-block" },
            { text: "#[AsBlockPattern]", link: "/api/as-block-pattern" },
            { text: "#[AsBlockCategory]", link: "/api/as-block-category" },
            { text: "#[AsBlockBinding]", link: "/api/as-block-binding" },
            { text: "#[AsAcfOptionsPage]", link: "/api/as-acf-options-page" },
            { text: "#[AsRestRoute]", link: "/api/as-rest-route" },
            { text: "#[AsRewriteRule]", link: "/api/as-rewrite-rule" },
            { text: "#[AsSettingsPage]", link: "/api/as-settings-page" },
            { text: "#[AsShortcode]", link: "/api/as-shortcode" },
            { text: "#[AsCliCommand]", link: "/api/as-cli-command" },
            { text: "#[AsImageSize]", link: "/api/as-image-size" },
            { text: "#[AsTwigExtension]", link: "/api/as-twig-extension" },
            { text: "#[AsDiscovery]", link: "/api/as-discovery" },
          ],
        },
        {
          text: "Interfaces",
          items: [
            { text: "BlockInterface", link: "/api/block-interface" },
            { text: "InteractiveBlockInterface", link: "/api/interactive-block-interface" },
            { text: "AcfBlockInterface", link: "/api/acf-block-interface" },
            { text: "AcfOptionsPageInterface", link: "/api/acf-options-page-interface" },
            { text: "ContextProviderInterface", link: "/api/context-provider-interface" },
            { text: "TemplateControllerInterface", link: "/api/template-controller-interface" },
            { text: "BlockPatternInterface", link: "/api/block-pattern-interface" },
            { text: "Arrayable", link: "/api/arrayable" },
            { text: "CacheInterface", link: "/api/cache-interface" },
          ],
        },
        {
          text: "DTOs & Traits",
          items: [
            { text: "HasToArray", link: "/api/has-to-array" },
            { text: "Data DTOs", link: "/api/data-dtos" },
            { text: "QueriesPostType", link: "/api/queries-post-type" },
          ],
        },
        {
          text: "Query",
          items: [{ text: "PostQueryBuilder", link: "/api/post-query-builder" }],
        },
        {
          text: "Core",
          items: [
            { text: "Kernel", link: "/api/kernel" },
            { text: "DiscoveryRunner", link: "/api/discovery-runner" },
            { text: "Helpers", link: "/api/helpers" },
            { text: "WebpackManifest", link: "/api/webpack-manifest" },
            { text: "PageCacheConfig", link: "/api/page-cache-config" },
          ],
        },
      ],
    },

    socialLinks: [{ icon: "github", link: "https://github.com/studiometa/foehn-framework" }],

    footer: {
      message: "Released under the MIT License.",
      copyright: "Copyright © 2024-present Studio Meta",
    },

    search: {
      provider: "local",
    },

    editLink: {
      pattern: "https://github.com/studiometa/foehn-framework/edit/main/docs/:path",
      text: "Edit this page on GitHub",
    },

    outline: {
      level: [2, 3],
    },
  },
});
