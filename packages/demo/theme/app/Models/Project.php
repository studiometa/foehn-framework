<?php

declare(strict_types=1);

namespace Demo\Models;

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Data\LinkData;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;
use Timber\Timber;

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
    supports: ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'],
    taxonomies: ['project_category', 'project_tag'],
)]
// The accessors below read these keys. Declaring them gives each a REST schema,
// which makes it bindable through core's `core/post-meta` source — no custom
// binding required. The `custom-fields` support above puts the keys in the
// REST response, so the block editor can read and save them. The `control` of
// each key gives it a field in the "Fields" meta box of the edit screen, one for
// each control Føhn draws.
#[AsPostMeta(
    key: 'client',
    type: 'string',
    description: 'Who commissioned the series',
    control: 'text',
    label: 'Client',
)]
#[AsPostMeta(key: 'year', type: 'integer', description: 'Year the series was shot', control: 'number', label: 'Year')]
#[AsPostMeta(key: 'location', type: 'string', description: 'Where it was shot', control: 'text', label: 'Location')]
#[AsPostMeta(
    key: 'camera',
    type: 'string',
    description: 'Camera body and film stock',
    control: 'text',
    label: 'Shot on',
)]
#[AsPostMeta(
    key: 'format',
    type: 'string',
    description: 'The format of the negatives or files',
    control: 'select',
    label: 'Format',
    options: self::FORMATS,
)]
#[AsPostMeta(
    key: 'film',
    type: 'string',
    description: 'YouTube or Vimeo URL of a film shown with the series',
    control: 'url',
    label: 'Film',
)]
#[AsPostMeta(key: 'credits', type: 'string', description: 'One person per line', control: 'textarea', label: 'Credits')]
#[AsPostMeta(
    key: 'prints',
    type: 'boolean',
    description: 'Prints of the series are for sale',
    control: 'checkbox',
    label: 'Prints',
)]
#[AsPostMeta(
    key: 'prints_email',
    type: 'string',
    description: 'Where to ask for a print',
    control: 'email',
    label: 'Prints email',
)]
#[AsPostMeta(
    key: 'prints_phone',
    type: 'string',
    description: 'Who to call for a print',
    control: 'tel',
    label: 'Prints phone',
)]
final class Project extends Post implements ConfiguresPostType
{
    /** The values of the `format` select, as value => label. */
    public const array FORMATS = [
        '35mm' => '35 mm film',
        'medium' => 'Medium format film',
        'digital' => 'Digital',
    ];

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
     * The URL of the film shown with the series, as an editor pastes it.
     *
     * Stored as the watch URL, not the embed URL: the template turns it into a
     * privacy-friendly embed with `video_embed()`, and a Vimeo URL works the same way.
     */
    public function film(): ?string
    {
        $film = $this->meta('film');

        return is_string($film) && $film !== '' ? $film : null;
    }

    /**
     * The format of the series, as the label the select shows.
     */
    public function format(): ?string
    {
        $format = $this->meta('format');

        return is_string($format) ? self::FORMATS[$format] ?? null : null;
    }

    /**
     * The people who worked on the series, one per line.
     */
    public function credits(): ?string
    {
        $credits = $this->meta('credits');

        return is_string($credits) && trim($credits) !== '' ? $credits : null;
    }

    /**
     * Whether prints of the series are for sale. The checkbox stores `1` or nothing.
     */
    public function prints(): bool
    {
        return (bool) $this->meta('prints');
    }

    public function printsEmail(): ?string
    {
        $email = $this->meta('prints_email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function printsPhone(): ?string
    {
        $phone = $this->meta('prints_phone');

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    /**
     * The phone number for a `tel:` link: the digits and the `+` of the country code.
     */
    public function printsPhoneLink(): ?string
    {
        $phone = $this->printsPhone();

        return $phone !== null ? preg_replace('/[^\d+]/', '', $phone) : null;
    }

    /**
     * What the press said, from the ACF field group Demo\Fields\ProjectPressFields.
     *
     * Null when ACF is not active or the quote is empty, so the page prints nothing
     * rather than an empty quote.
     *
     * @return array{quote: string, source: string, link: ?LinkData, style: string}|null
     */
    public function press(): ?array
    {
        if (!function_exists('get_field')) {
            return null;
        }

        $quote = get_field('press_quote', $this->ID);

        if (!is_string($quote) || $quote === '') {
            return null;
        }

        $link = get_field('press_link', $this->ID);
        $link = is_array($link) ? $link : [];

        return [
            'quote' => $quote,
            'source' => (string) get_field('press_source', $this->ID),
            'link' => LinkData::fromAcf(is_array($link['link'] ?? null) ? $link['link'] : null),
            'style' => (string) ($link['style'] ?? 'link'),
        ];
    }

    /**
     * @return list<\Timber\Term>
     */
    public function projectTags(): array
    {
        return $this->terms('project_tag');
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
            'Format' => $this->format(),
        ];

        return array_filter($facts, static fn(?string $value): bool => $value !== null);
    }

    /**
     * Every photograph in the series, the cover included.
     *
     * @return list<Photograph>
     */
    public function photographs(): array
    {
        $ids = get_post_meta($this->ID, 'gallery', true);

        if (!is_array($ids)) {
            return [];
        }

        $photographs = [];

        foreach ($ids as $id) {
            // Through the class map, so each one is a Photograph.
            $image = Timber::get_post((int) $id);

            if ($image instanceof Photograph) {
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
