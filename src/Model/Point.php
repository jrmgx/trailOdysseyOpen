<?php

namespace App\Model;

use App\Entity\GeoPoint;
use App\Helper\GeoHelper;
use Symfony\Component\Serializer\Attribute\Groups;

class Point
{
    public function __construct(
        #[Groups(['public'])]
        public string $lat,
        #[Groups(['public'])]
        public string $lon,
        #[Groups(['public'])]
        public ?string $el = null,
    ) {
    }

    public function __toString(): string
    {
        return "[$this->lat, $this->lon, $this->el]";
    }

    public function getId(): string
    {
        return sha1($this->lat . '-' . $this->lon . '-' . $this->el);
    }

    public function hasElevation(): bool
    {
        return null !== $this->el && '' !== $this->el;
    }

    public function toGeoPoint(): GeoPoint
    {
        $geoPoint = new GeoPoint();
        $geoPoint->setLat($this->lat);
        $geoPoint->setLon($this->lon);

        return $geoPoint;
    }

    public function equals(mixed $point): bool
    {
        if (!$point instanceof self) {
            return false;
        }

        return $this->lat === $point->lat && $this->lon === $point->lon && $this->el === $point->el;
    }

    public function equalsWithoutElevation(mixed $point): bool
    {
        if (!$point instanceof self) {
            return false;
        }

        return $this->lat === $point->lat && $this->lon === $point->lon;
    }

    /**
     * @param int $delta Distance in meters
     */
    public function isCloseTo(self $point, int $delta): bool
    {
        return GeoHelper::calculateDistanceFast($this, $point) <= $delta;
    }
}
