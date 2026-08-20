<?php

declare(strict_types=1);

namespace Demo\Models;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Timber\Image;

/**
 * A body of work — one series of photographs, shown on its own page.
 */
#[AsPostType(
    name: 'project',
    singular: 'Project',
    plural: 'Projects',
    public: true,
    hasArchive: true,
    showInRest: true,
    menuIcon: 'dashicons-camera-alt',
    supports: ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
    taxonomies: ['project_category', 'project_tag'],
)]
// The accessors below read these keys. Declaring them gives each a REST schema,
// which is what puts it in the block editor and makes it bindable through core's
// `core/post-meta` source — no custom binding required.
#[AsPostMeta(key: 'client', type: 'string', description: 'Who commissioned the series')]
#[AsPostMeta(key: 'year', type: 'integer', description: 'Year the series was shot')]
#[AsPostMeta(key: 'location', type: 'string', description: 'Where it was shot')]
#[AsPostMeta(key: 'camera', type: 'string', description: 'Camera body and film stock')]
final class Project extends Post implements ConfiguresPostType
{
    public static function configurePostType(PostTypeBuilder $builder): PostTypeBuilder
    {
        return $builder->setRewrite(['slug' => 'projects', 'with_front' => false])->setMenuPosition(5);
    }

    public function client(): ?string
    {
        $client = $this->meta('client');

        return is_string($client) && $client !== '' ? $client : null;
    }

    public function year(): ?int
    {
        $year = $this->meta('year');

        return is_numeric($year) ? (int) $year : null;
    }

    public function location(): ?string
    {
        $location = $this->meta('location');

        return is_string($location) && $location !== '' ? $location : null;
    }

    public function camera(): ?string
    {
        $camera = $this->meta('camera');

        return is_string($camera) && $camera !== '' ? $camera : null;
    }

    /**
     * The facts listed beside the title, in the order the page prints them.
     *
     * Built here rather than in the template so the single page and the card agree
     * on what a project's metadata is.
     *
     * @return array<string, string>
     */
    public function facts(): array
    {
        $facts = [
            'Client' => $this->client(),
            'Year' => $this->year() !== null ? (string) $this->year() : null,
            'Location' => $this->location(),
            'Shot on' => $this->camera(),
        ];

        return array_filter($facts, static fn(?string $value): bool => $value !== null);
    }

    /**
     * Every photograph in the series, the cover included.
     *
     * @return list<Image>
     */
    public function photographs(): array
    {
        $ids = get_post_meta($this->ID, 'gallery', true);

        if (!is_array($ids)) {
            return [];
        }

        $photographs = [];

        foreach ($ids as $id) {
            $image = Image::build(get_post((int) $id));

            if ($image instanceof Image) {
                $photographs[] = $image;
            }
        }

        return $photographs;
    }

    /**
     * How many photographs the series holds, which the index prints beside its title.
     */
    public function photographCount(): int
    {
        return count($this->photographs());
    }

    /**
     * @return list<\Timber\Term>
     */
    public function categories(): array
    {
        return $this->terms('project_category');
    }
}
