<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\Search;

use Studiometa\Foehn\Places\Config\PlacesConfig;
use Studiometa\Foehn\Places\ValueObjects\BoundingBox;
use Studiometa\Foehn\Places\ValueObjects\PlaceCoordinates;

/**
 * The normalized request of the place archive.
 *
 * Parsed from the raw query string, as SectionRequest does, so a repeated parameter
 * stays visible. Every rule either normalizes a value or refuses the request: a
 * malformed value is never turned into a guessed, wider query.
 */
final readonly class PlaceSearchRequest
{
    public const int MAX_QUERY_LENGTH = 100;

    public const int MAX_TERMS = 20;

    /** The length of one number of `near` or `bbox`: a JavaScript double fits. */
    public const int MAX_NUMBER_LENGTH = 32;

    public const array VIEWS = ['list', 'map'];

    private const array SCALARS = ['q', 'view', 'near', 'radius', 'bbox'];

    /**
     * @param array<string, list<string>> $terms Selected term slugs, by taxonomy
     */
    public function __construct(
        public ?string $q = null,
        public string $view = 'list',
        public ?PlaceCoordinates $near = null,
        public ?int $radius = null,
        public ?BoundingBox $bbox = null,
        public array $terms = [],
    ) {}

    /**
     * Parse a raw query string.
     *
     * @param list<string> $taxonomies The facet taxonomies, whose query vars carry term slugs
     * @param null|callable(string, list<string>): bool $termsExist Whether every slug exists in a taxonomy
     * @throws InvalidPlaceSearch When the request breaks a rule
     */
    public static function fromQueryString(
        string $query,
        PlacesConfig $config,
        array $taxonomies = [],
        ?callable $termsExist = null,
    ): self {
        $scalars = [];
        $terms = [];

        foreach (self::pairs($query) as [$key, $value]) {
            $name = str_ends_with($key, '[]') ? substr($key, 0, -2) : $key;

            if (in_array($name, self::SCALARS, true)) {
                if ($name !== $key || array_key_exists($name, $scalars)) {
                    throw new InvalidPlaceSearch(sprintf('"%s" is given more than once.', $name));
                }

                $scalars[$name] = trim($value);

                continue;
            }

            if (in_array($name, $taxonomies, true)) {
                foreach (explode(',', $value) as $slug) {
                    $slug = strtolower(trim($slug));

                    if ($slug !== '') {
                        $terms[$name][] = $slug;
                    }
                }
            }
        }

        $q = self::text($scalars['q'] ?? '');
        $view = $scalars['view'] ?? '';
        $view = $view === '' ? 'list' : $view;

        if (!in_array($view, self::VIEWS, true)) {
            throw new InvalidPlaceSearch(sprintf('"%s" is not a view.', $view));
        }

        $nearValue = $scalars['near'] ?? '';
        $radiusValue = $scalars['radius'] ?? '';
        $bboxValue = $scalars['bbox'] ?? '';

        if (!$config->nearSearch && ($nearValue !== '' || $radiusValue !== '')) {
            throw new InvalidPlaceSearch('The search around an address is off.');
        }

        if (!$config->areaSearch && $bboxValue !== '') {
            throw new InvalidPlaceSearch('The search of a map area is off.');
        }

        if ($nearValue !== '' && $bboxValue !== '') {
            throw new InvalidPlaceSearch('"near" and "bbox" cannot be combined.');
        }

        $near = $nearValue === '' ? null : self::near($nearValue);
        $radius = $radiusValue === '' ? null : self::radius($radiusValue, $config);

        foreach ($terms as $taxonomy => $slugs) {
            $slugs = array_values(array_unique($slugs));

            if (count($slugs) > self::MAX_TERMS) {
                throw new InvalidPlaceSearch(sprintf('Too many "%s" terms.', $taxonomy));
            }

            foreach ($slugs as $slug) {
                if (preg_match('/^[a-z0-9%_-]{1,200}$/', $slug) !== 1) {
                    throw new InvalidPlaceSearch(sprintf('"%s" is not a term slug.', $slug));
                }
            }

            if ($termsExist !== null && !$termsExist($taxonomy, $slugs)) {
                throw new InvalidPlaceSearch(sprintf('A "%s" term does not exist.', $taxonomy));
            }

            $terms[$taxonomy] = $slugs;
        }

        return new self(
            q: $q,
            view: $view,
            near: $near,
            radius: $near === null ? null : $radius ?? $config->defaultRadius,
            bbox: $bboxValue === '' ? null : self::bbox($bboxValue),
            terms: $terms,
        );
    }

    /**
     * Whether a position (`near` or `bbox`) scopes the search.
     */
    public function isPositioned(): bool
    {
        return $this->near !== null || $this->bbox !== null;
    }

    /**
     * The request as query arguments, for links that keep it.
     *
     * The terms of a taxonomy are one comma-separated value (`place_type=a,b`), the form
     * the page cache keys: `http_build_query()` and `add_query_arg()` write a list as
     * `place_type[0]=a`, which the parser does not read.
     *
     * @return array<string, string>
     */
    public function toQueryArgs(): array
    {
        $args = [];

        if ($this->q !== null) {
            $args['q'] = $this->q;
        }

        if ($this->view !== 'list') {
            $args['view'] = $this->view;
        }

        if ($this->near !== null) {
            $args['near'] = (string) $this->near;
            $args['radius'] = (string) $this->radius;
        }

        if ($this->bbox !== null) {
            $args['bbox'] = (string) $this->bbox;
        }

        foreach ($this->terms as $taxonomy => $slugs) {
            $args[$taxonomy] = implode(',', $slugs);
        }

        return $args;
    }

    /**
     * The key and value of each pair of a query string, decoded.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function pairs(string $query): array
    {
        $pairs = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $pairs[] = [urldecode($key), urldecode($value)];
        }

        return $pairs;
    }

    private static function text(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > self::MAX_QUERY_LENGTH) {
            throw new InvalidPlaceSearch(sprintf('"q" is longer than %d characters.', self::MAX_QUERY_LENGTH));
        }

        return $value;
    }

    private static function near(string $value): PlaceCoordinates
    {
        $numbers = self::numbers($value, 2, 'near');
        $coordinates = PlaceCoordinates::from($numbers[0], $numbers[1]);

        if ($coordinates === null) {
            throw new InvalidPlaceSearch('"near" is out of range.');
        }

        return $coordinates->round(5);
    }

    private static function radius(string $value, PlacesConfig $config): int
    {
        if (!ctype_digit($value) || !in_array((int) $value, $config->radiusOptions, true)) {
            throw new InvalidPlaceSearch(sprintf('"%s" is not one of the radius options.', $value));
        }

        return (int) $value;
    }

    private static function bbox(string $value): BoundingBox
    {
        [$west, $south, $east, $north] = self::numbers($value, 4, 'bbox');
        $bbox = BoundingBox::fromBounds($west, $south, $east, $north);

        if ($bbox === null) {
            throw new InvalidPlaceSearch('"bbox" is out of range.');
        }

        return $bbox;
    }

    /**
     * The numbers of a comma-separated list.
     *
     * A number is any decimal form, so the raw doubles of a map (`0.30000000000000004`,
     * `1e-7` from JavaScript) are read. They are rounded later.
     *
     * @return list<float>
     */
    private static function numbers(string $value, int $count, string $name): array
    {
        $parts = explode(',', $value);

        if (count($parts) !== $count) {
            throw new InvalidPlaceSearch(sprintf('"%s" needs %d numbers.', $name, $count));
        }

        $numbers = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if (strlen($part) > self::MAX_NUMBER_LENGTH || !is_numeric($part)) {
                throw new InvalidPlaceSearch(sprintf('"%s" is not a list of numbers.', $name));
            }

            $numbers[] = (float) $part;
        }

        return $numbers;
    }
}
