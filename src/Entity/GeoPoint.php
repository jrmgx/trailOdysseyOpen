<?php

namespace App\Entity;

use App\Model\Point;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This GeoPoint is only used for POI positioning and does not have elevation.
 * It is also made to be embedded into Entities.
 */
#[ORM\Embeddable]
class GeoPoint
{
    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Regex('/^(\+|-)?\d*\.?\d*$/')] // https://regex101.com/r/VR7qZe/1
    private string $lat;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Regex('/^(\+|-)?\d*\.?\d*$/')]
    private string $lon;

    public function toPoint(): Point
    {
        return new Point($this->lat, $this->lon);
    }

    public function getLat(): string
    {
        return $this->lat;
    }

    public function setLat(string $lat): self
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLon(): string
    {
        return $this->lon;
    }

    public function setLon(string $lon): self
    {
        $this->lon = $lon;

        return $this;
    }
}
