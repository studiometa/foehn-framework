<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\ValueObjects;

/**
 * A map area: west, south, east and north.
 *
 * The longitudes come from raw map bounds, which can be outside `-180..180` when the
 * map shows copies of the world. A span of 360° or more covers every longitude.
 * Otherwise both longitudes are wrapped into `-180..180`, and an east below the west
 * crosses the antimeridian.
 */
final readonly class BoundingBox
{
    private function __construct(
        public float $west,
        public float $south,
        public float $east,
        public float $north,
    ) {}

    /**
     * The normalized area of raw map bounds, or null when the latitudes are out of
     * range or south is not below north.
     */
    public static function fromBounds(float $west, float $south, float $east, float $north): ?self
    {
        if ($south < -90 || $north > 90 || $south >= $north || $east <= ($west - 360)) {
            return null;
        }

        if (($east - $west) >= 360) {
            return new self(-180.0, round($south, 5), 180.0, round($north, 5));
        }

        return new self(
            round(self::wrap($west, false), 5),
            round($south, 5),
            round(self::wrap($east, true), 5),
            round($north, 5),
        );
    }

    /**
     * Whether coordinates are inside the area, edges included.
     */
    public function contains(PlaceCoordinates $coordinates): bool
    {
        if ($coordinates->latitude < $this->south || $coordinates->latitude > $this->north) {
            return false;
        }

        $longitude = $coordinates->longitude;

        if ($this->west <= $this->east) {
            return $longitude >= $this->west && $longitude <= $this->east;
        }

        return $longitude >= $this->west || $longitude <= $this->east;
    }

    /**
     * `west,south,east,north`, the form of the `bbox` request parameter.
     */
    public function __toString(): string
    {
        return implode(',', array_map(PlaceCoordinates::decimal(...), [
            $this->west,
            $this->south,
            $this->east,
            $this->north,
        ]));
    }

    /**
     * A longitude in `-180..180`: `[-180, 180)` for a west edge, `(-180, 180]` for an
     * east edge, so a box that ends on 180 does not cross the antimeridian.
     */
    private static function wrap(float $longitude, bool $east): float
    {
        $wrapped = fmod(fmod($longitude + 180, 360) + 360, 360) - 180;

        return $east && $wrapped === -180.0 ? 180.0 : $wrapped;
    }
}
