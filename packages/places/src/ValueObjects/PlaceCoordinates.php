<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Places\ValueObjects;

/**
 * A valid latitude and longitude.
 *
 * There is no "no coordinates" value: a place without valid coordinates has none
 * (`null`), and is never placed at `[0, 0]`. Zero itself is a valid value.
 */
final readonly class PlaceCoordinates
{
    /** The mean radius of the Earth, in metres. */
    public const float EARTH_RADIUS = 6_371_008.8;

    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * The coordinates of two values, or null when one is missing, not a number or out
     * of range.
     */
    public static function from(mixed $latitude, mixed $longitude): ?self
    {
        $latitude = self::number($latitude);
        $longitude = self::number($longitude);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return new self($latitude, $longitude);
    }

    /**
     * A finite number from a stored or posted value, or null.
     */
    public static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (!is_string($value) || !is_numeric(trim($value))) {
            return null;
        }

        $number = filter_var(trim($value), FILTER_VALIDATE_FLOAT);

        return $number !== false && is_finite($number) ? $number : null;
    }

    /**
     * The order Mapbox uses.
     *
     * @return array{0: float, 1: float} [longitude, latitude]
     */
    public function toLngLat(): array
    {
        return [$this->longitude, $this->latitude];
    }

    /**
     * The great-circle distance to other coordinates, in metres (Haversine formula).
     */
    public function distanceTo(self $other): float
    {
        $phi1 = deg2rad($this->latitude);
        $phi2 = deg2rad($other->latitude);
        $deltaPhi = deg2rad($other->latitude - $this->latitude);
        $deltaLambda = deg2rad($other->longitude - $this->longitude);

        $a = sin($deltaPhi / 2) ** 2 + (cos($phi1) * cos($phi2) * (sin($deltaLambda / 2) ** 2));

        return 2 * self::EARTH_RADIUS * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * The coordinates rounded to a number of decimal places.
     */
    public function round(int $precision): self
    {
        return new self(round($this->latitude, $precision), round($this->longitude, $precision));
    }

    /**
     * `latitude,longitude`, the form of the `near` request parameter.
     */
    public function __toString(): string
    {
        return self::decimal($this->latitude) . ',' . self::decimal($this->longitude);
    }

    /**
     * A coordinate in plain decimal notation, to ten decimals at most.
     *
     * PHP prints a small float with an exponent (`1.0E-5`), which is not the plain
     * number a request parameter or a data attribute needs.
     */
    public static function decimal(float $value): string
    {
        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
