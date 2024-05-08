<?php

namespace App\Entity;

use App\Model\Point;
use App\Repository\SegmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SegmentRepository::class)]
class Segment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'segments')]
    #[ORM\JoinColumn(nullable: false)]
    private Trip $trip;

    /** @var array<array{lat: string, lon: string, el?: ?string}>|array<Point> */
    #[ORM\Column]
    private array $points = [];

    /** @var array{minLat: string, maxLat: string, minLon: string, maxLon: string} */
    #[ORM\Column]
    private array $boundingBox = ['minLat' => '0', 'maxLat' => '0', 'minLon' => '0', 'maxLon' => '0'];

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

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

    /**
     * @return array<Point>
     */
    public function getPoints(): array
    {
        if (0 === \count($this->points)) {
            return [];
        }

        if ($this->points[0] instanceof Point) {
            /** @var array<Point> */
            return $this->points;
        }

        $points = [];
        /** @var array{lat: string, lon: string, el?: ?string} $p */
        foreach ($this->points as $p) {
            $points[] = new Point($p['lat'], $p['lon'], $p['el'] ?? null);
        }

        return $points;
    }

    /**
     * @param array<Point> $points
     */
    public function setPoints(array $points): self
    {
        $this->points = $points;

        return $this;
    }

    /**
     * @return array{minLat: string, maxLat: string, minLon: string, maxLon: string}
     */
    public function getBoundingBox(): array
    {
        return $this->boundingBox;
    }

    /**
     * @param array{minLat: string, maxLat: string, minLon: string, maxLon: string} $boundingBox
     */
    public function setBoundingBox(array $boundingBox): self
    {
        $this->boundingBox = $boundingBox;

        return $this;
    }
}
