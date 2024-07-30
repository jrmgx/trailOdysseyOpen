<?php

namespace App\Entity;

use App\Helper\GeoHelper;
use App\Model\Point;
use App\Repository\RoutingRepository;
use App\Service\GeoElevationService;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoutingRepository::class)]
class Routing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'routings')]
    #[ORM\JoinColumn(nullable: false)]
    private Trip $trip;

    #[ORM\OneToOne(inversedBy: 'routingOut')]
    #[ORM\JoinColumn(nullable: false)]
    private Stage $startStage;

    #[ORM\OneToOne(inversedBy: 'routingIn')]
    #[ORM\JoinColumn(nullable: false)]
    private Stage $finishStage;

    #[ORM\Column(nullable: true)]
    private ?int $distance = null;

    #[ORM\Column]
    private bool $asTheCrowFly = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private mixed $pathPointsStore = null;

    #[ORM\Column(nullable: true)]
    private ?int $elevationPositive = null;

    #[ORM\Column(nullable: true)]
    private ?int $elevationNegative = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStartStage(): Stage
    {
        return $this->startStage;
    }

    public function setStartStage(Stage $startStage): self
    {
        $this->startStage = $startStage;

        return $this;
    }

    public function getFinishStage(): Stage
    {
        return $this->finishStage;
    }

    public function setFinishStage(Stage $finishStage): self
    {
        $this->finishStage = $finishStage;

        return $this;
    }

    public function getDistance(): ?int
    {
        return $this->distance;
    }

    public function setDistance(?int $distance): self
    {
        $this->distance = $distance;

        return $this;
    }

    public function getAsTheCrowFly(): bool
    {
        return $this->asTheCrowFly;
    }

    public function setAsTheCrowFly(bool $asTheCrowFly): self
    {
        $this->asTheCrowFly = $asTheCrowFly;

        return $this;
    }

    public function pathPointsNotEmpty(): bool
    {
        return null !== $this->pathPointsStore;
    }

    /**
     * @return ?array<Point>
     */
    public function getPathPoints(): ?array
    {
        if (null === $this->pathPointsStore) {
            return null;
        }

        $data = json_decode($this->pathPointsStore, true);

        $points = array_map(fn (array $p) => new Point($p[0], $p[1], $p[2] ?? null), $data);

        $startStagePoint = $this->startStage->getPoint()->toPoint();
        /** @var Point $firstPoint */
        $firstPoint = current($points);
        $distanceToFirst = GeoHelper::calculateDistance($startStagePoint, $firstPoint);
        /** @var Point $lastPoint */
        $lastPoint = end($points);
        $distanceToLast = GeoHelper::calculateDistance($startStagePoint, $lastPoint);
        if ($distanceToFirst > $distanceToLast) {
            $points = array_reverse($points);
        }

        return $points;
    }

    /**
     * @param ?array<Point> $pathPoints
     */
    public function setPathPoints(?array $pathPoints): self
    {
        if (null === $pathPoints) {
            $this->pathPointsStore = null;

            return $this;
        }
        $data = array_map(fn (Point $point) => [$point->lat, $point->lon, GeoElevationService::nonNegative($point->el)], $pathPoints);
        $this->pathPointsStore = json_encode($data);

        return $this;
    }

    public function getElevationPositive(): ?int
    {
        return $this->elevationPositive;
    }

    public function setElevationPositive(?int $elevationPositive): self
    {
        $this->elevationPositive = $elevationPositive;

        return $this;
    }

    public function getElevationNegative(): ?int
    {
        return $this->elevationNegative;
    }

    public function setElevationNegative(?int $elevationNegative): self
    {
        $this->elevationNegative = $elevationNegative;

        return $this;
    }
}
