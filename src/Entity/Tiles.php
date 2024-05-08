<?php

namespace App\Entity;

use App\Repository\TilesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TilesRepository::class)]
class Tiles
{
    public const OSM_DEFAULT = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const OSM_DEFAULT_DESC = '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a>';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    #[Groups(['leaflet'])]
    private string $name = 'Open Street Map';

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private string $url = self::OSM_DEFAULT;
    // TODO assert must be https? and not our domain

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['leaflet'])]
    private ?string $description = self::OSM_DEFAULT_DESC;
    // TODO prevent HTML add markdown => sure??

    #[ORM\Column]
    #[Groups(['leaflet'])]
    private bool $overlay = false;

    #[ORM\Column]
    private bool $public = false;

    #[ORM\Column]
    #[Groups(['leaflet'])]
    private bool $geoJson = false;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(inversedBy: 'tiles')]
    #[ORM\JoinColumn(nullable: false)]
    private Trip $trip;

    /** @var ?string jsonToHtml string @see jsonToHtml.js */
    #[Assert\Json]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['leaflet'])]
    private ?string $geoJsonHtml = null;

    #[ORM\Column]
    private int $position = 1;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Groups(['leaflet'])]
    public function getProxyUrl(): string
    {
        return "/t/p/$this->id/{x}/{y}/{z}";
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getTrip(): Trip
    {
        return $this->trip;
    }

    public function setTrip(Trip $trip): self
    {
        $this->trip = $trip;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getOverlay(): bool
    {
        return $this->overlay;
    }

    public function setOverlay(bool $overlay): self
    {
        $this->overlay = $overlay;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function getGeoJson(): bool
    {
        return $this->geoJson;
    }

    public function setGeoJson(bool $geoJson): self
    {
        $this->geoJson = $geoJson;

        return $this;
    }

    public function getGeoJsonHtml(): ?string
    {
        return $this->geoJsonHtml;
    }

    public function setGeoJsonHtml(?string $geoJsonHtml): static
    {
        $this->geoJsonHtml = $geoJsonHtml;

        return $this;
    }
}
