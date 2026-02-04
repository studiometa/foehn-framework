import { defineConfig } from "vitepress";

export default defineConfig({
  title: "WP Tempest",
  description:
    "A modern WordPress framework powered by Tempest, featuring attribute-based auto-discovery",

  base: "/wp-tempest/",

  head: [["link", { rel: "icon", href: "/wp-tempest/favicon.ico" }]],

  themeConfig: {
    logo: "/logo.svg",

    nav: [
      { text: "Guide", link: "/guide/getting-started" },
      { text: "API Reference", link: "/api/" },
      {
        text: "GitHub",
        link: "https://github.com/studiometa/wp-tempest",
      },
    ],

    sidebar: {
      "/guide/": [
        {
          text: "Introduction",
          items: [
            { text: "Getting Started", link: "/guide/getting-started" },
            { text: "Installation", link: "/guide/installation" },
          ],
        },
        {
          text: "Core Concepts",
          items: [
            { text: "Hooks", link: "/guide/hooks" },
            { text: "Post Types", link: "/guide/post-types" },
            { text: "Taxonomies", link: "/guide/taxonomies" },
          ],
        },
        {
          text: "Views & Templates",
          items: [
            { text: "View Composers", link: "/guide/view-composers" },
            { text: "Template Controllers", link: "/guide/template-controllers" },
          ],
        },
        {
          text: "Blocks",
          items: [
            { text: "ACF Blocks", link: "/guide/acf-blocks" },
            { text: "Native Blocks", link: "/guide/native-blocks" },
            { text: "Block Patterns", link: "/guide/block-patterns" },
          ],
        },
        {
          text: "Advanced",
          items: [
            { text: "REST API", link: "/guide/rest-api" },
            { text: "Shortcodes", link: "/guide/shortcodes" },
            { text: "CLI Commands", link: "/guide/cli-commands" },
            { text: "Discovery Cache", link: "/guide/discovery-cache" },
          ],
        },
        {
          text: "Migration",
          items: [
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
            { text: "#[AsTaxonomy]", link: "/api/as-taxonomy" },
            { text: "#[AsViewComposer]", link: "/api/as-view-composer" },
            { text: "#[AsTemplateController]", link: "/api/as-template-controller" },
            { text: "#[AsBlock]", link: "/api/as-block" },
            { text: "#[AsAcfBlock]", link: "/api/as-acf-block" },
            { text: "#[AsBlockPattern]", link: "/api/as-block-pattern" },
            { text: "#[AsBlockCategory]", link: "/api/as-block-category" },
            { text: "#[AsRestRoute]", link: "/api/as-rest-route" },
            { text: "#[AsShortcode]", link: "/api/as-shortcode" },
            { text: "#[AsCliCommand]", link: "/api/as-cli-command" },
          ],
        },
        {
          text: "Interfaces",
          items: [
            { text: "BlockInterface", link: "/api/block-interface" },
            { text: "InteractiveBlockInterface", link: "/api/interactive-block-interface" },
            { text: "AcfBlockInterface", link: "/api/acf-block-interface" },
            { text: "ViewComposerInterface", link: "/api/view-composer-interface" },
            { text: "TemplateControllerInterface", link: "/api/template-controller-interface" },
            { text: "BlockPatternInterface", link: "/api/block-pattern-interface" },
          ],
        },
        {
          text: "Core",
          items: [
            { text: "Kernel", link: "/api/kernel" },
            { text: "Helpers", link: "/api/helpers" },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: "github", link: "https://github.com/studiometa/wp-tempest" },
    ],

    footer: {
      message: "Released under the MIT License.",
      copyright: "Copyright © 2024-present Studio Meta",
    },

    search: {
      provider: "local",
    },

    editLink: {
      pattern: "https://github.com/studiometa/wp-tempest/edit/main/docs/:path",
      text: "Edit this page on GitHub",
    },
  },
});
