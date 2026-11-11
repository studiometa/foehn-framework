<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Inspect;

use InvalidArgumentException;
use Studiometa\Foehn\Discovery\DiscoveryRunner;

/**
 * The project model: what a Føhn project declares, as plain data.
 *
 * Built from the items discovery already collected, so it reads the attributes the
 * code declares, and it registers nothing. Only plugins, theme supports, the Twig
 * files and the Vite build come from WordPress or the filesystem, because no
 * attribute declares them, and the context keys a class adds are read from its
 * source (see ContextKeys).
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
        'settings',
        'blocks',
        'patterns',
        'bindings',
        'templates',
        'views',
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

    private Templates $templates;

    private Environment $environment;

    public function __construct(DiscoveryRunner $runner)
    {
        $items = new DiscoveredItems($runner);

        $this->contentTypes = new ContentTypes($items);
        $this->registrations = new Registrations($items);
        $this->templates = new Templates($items);
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
     * What each section holds, by name: the name of each row, in report order.
     *
     * Short enough to read whole, so it is where a reader starts and picks the
     * sections to read in full.
     *
     * @return array<string, list<string>>
     */
    public function summary(): array
    {
        $summary = [];

        foreach (self::SECTIONS as $name) {
            $summary[$name] = self::names($name, $this->section($name));
        }

        return $summary;
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
            'settings' => $this->registrations->settings(),
            'blocks' => $this->registrations->blocks(),
            'patterns' => $this->registrations->patterns(),
            'bindings' => $this->registrations->bindings(),
            'templates' => $this->templates->templates(),
            'views' => $this->templates->views(),
            'context-providers' => $this->templates->contextProviders(),
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

    /**
     * The name of each row of a section: the field a reader asks for it by.
     *
     * @param array<mixed> $data
     * @return list<string>
     */
    private static function names(string $section, array $data): array
    {
        $rows = $section === 'vite' ? $data['entries'] ?? [] : $data;
        $names = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $names[] = (string) match ($section) {
                'settings' => $row['page'],
                'templates' => $row['template'],
                'views' => $row['view'],
                'context-providers' => $row['class'],
                'menus' => $row['location'],
                'rewrite-rules' => $row['regex'],
                'rest-routes' => $row['method'] . ' ' . $row['path'],
                'cli-commands' => $row['command'],
                'theme-supports' => $row['feature'],
                'vite' => $row['entry'],
                default => $row['name'],
            };
        }

        return $names;
    }
}
