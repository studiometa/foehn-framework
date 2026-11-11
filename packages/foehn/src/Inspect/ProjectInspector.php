<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use InvalidArgumentException;
use Studiometa\Foehn\Discovery\DiscoveryRunner;

/**
 * The project model: what a Føhn project declares, as plain data.
 *
 * Built from the items discovery already collected, so it reads the attributes the
 * code declares rather than parsing source, and it registers nothing. Only plugins,
 * theme supports and the Vite build come from WordPress or the filesystem, because
 * no attribute declares them.
 *
 * `wp foehn inspect` renders it. It holds no WP-CLI code so that another adapter can
 * hand the same model to a coding agent by other means.
 */
final readonly class ProjectInspector
{
    /** @var list<string> Section names, in report order */
    public const array SECTIONS = [
        'post-types',
        'taxonomies',
        'blocks',
        'patterns',
        'bindings',
        'templates',
        'context-providers',
        'menus',
        'image-sizes',
        'rewrite-rules',
        'rest-routes',
        'cli-commands',
        'plugins',
        'theme-supports',
        'vite',
    ];

    private ContentTypes $contentTypes;

    private Registrations $registrations;

    private Environment $environment;

    public function __construct(DiscoveryRunner $runner)
    {
        $items = new DiscoveredItems($runner);

        $this->contentTypes = new ContentTypes($items);
        $this->registrations = new Registrations($items);
        $this->environment = new Environment();
    }

    /**
     * Every section, keyed by name.
     *
     * @return array<string, array<mixed>>
     */
    public function all(): array
    {
        $report = [];

        foreach (self::SECTIONS as $name) {
            $report[$name] = $this->section($name);
        }

        return $report;
    }

    /**
     * One section: a list of rows, or for `vite` a single record.
     *
     * @return array<mixed>
     * @throws InvalidArgumentException When the section does not exist
     */
    public function section(string $name): array
    {
        return match ($name) {
            'post-types' => $this->contentTypes->postTypes(),
            'taxonomies' => $this->contentTypes->taxonomies(),
            'blocks' => $this->registrations->blocks(),
            'patterns' => $this->registrations->patterns(),
            'bindings' => $this->registrations->bindings(),
            'templates' => $this->registrations->templates(),
            'context-providers' => $this->registrations->contextProviders(),
            'menus' => $this->registrations->menus(),
            'image-sizes' => $this->registrations->imageSizes(),
            'rewrite-rules' => $this->registrations->rewriteRules(),
            'rest-routes' => $this->registrations->restRoutes(),
            'cli-commands' => $this->registrations->cliCommands(),
            'plugins' => $this->environment->plugins(),
            'theme-supports' => $this->environment->themeSupports(),
            'vite' => $this->environment->vite(),
            default => throw new InvalidArgumentException(sprintf(
                "Unknown section '%s'. Available sections: %s.",
                $name,
                implode(', ', self::SECTIONS),
            )),
        };
    }
}
