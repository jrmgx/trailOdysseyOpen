<?php

namespace App\Model;

use App\Entity\Routing;
use App\Entity\Segment;
use App\Helper\GeoHelper;

class Path
{
    private ?int $distance = null;

    /**
     * @param array<Point> $points
     * @param array<Path>  $children
     */
    public function __construct(
        private array $points,
        private array $children = [],
        private ?int $id = null,
        private ?string $name = null, // For debug only
    ) {
        if (0 === \count($this->points)) {
            throw new \Exception('Path must contain at least one Point.');
        }
    }

    public static function fromSegment(Segment $segment): self
    {
        return new self($segment->getPoints(), id: $segment->getId(), name: $segment->getName());
    }

    public static function fromRouting(Routing $routing): ?self
    {
        $points = $routing->getPathPoints();
        if (null === $points || 0 === \count($points)) {
            return null;
        }

        return new self($points, id: $routing->getId(), name: 'Routing#' . $routing->getId());
    }

    public function toSegment(): Segment
    {
        $segment = new Segment();
        $segment->setPoints($this->getPoints());
        $segment->setName($this->getName() ?? '');

        return $segment;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
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

    /**
     * @return array<Path>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * @return array<Point>
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @param array<Point> $points
     */
    public function setPoints(array $points): self
    {
        $this->points = $points;

        return $this;
    }

    public function appendPoint(Point $point): self
    {
        $this->points[] = $point;

        return $this;
    }

    /**
     * Note: this distance does not include elevation.
     */
    public function getDistance(): int
    {
        if (null === $this->distance) {
            $this->distance = GeoHelper::calculateDistanceFromPoints($this->points);
        }

        return $this->distance;
    }

    public function __toString(): string
    {
        $firstPoint = reset($this->points);
        $lastPoint = end($this->points);
        $name = '';
        if ($this->name) {
            $name = ' (' . $this->name . ')';
        }

        return 'Path' . $name . ' [' . $firstPoint . ' => ' . $lastPoint . ']';
    }

    public function containPoint(Point $point): bool
    {
        foreach ($this->points as $p) {
            if ($p->equals($point)) {
                return true;
            }
        }

        return false;
    }

    public function size(): int
    {
        return \count($this->points);
    }

    public function getFirstPoint(): Point
    {
        return $this->points[0];
    }

    public function getLastPoint(): Point
    {
        return $this->points[$this->size() - 1];
    }

    public function reverse(): void
    {
        $this->points = array_reverse($this->points);
    }

    public function equals(mixed $path): bool
    {
        if (!$path instanceof self) {
            return false;
        }

        if (\count($this->points) !== \count($path->points)) {
            return false;
        }

        return $this->points === $path->points;
    }

    public function extremitiesCloseToExtremitiesOf(self $path, int $delta): bool
    {
        return
            $this->getFirstPoint()->isCloseTo($path->getFirstPoint(), $delta)
            || $this->getLastPoint()->isCloseTo($path->getFirstPoint(), $delta)
            || $this->getFirstPoint()->isCloseTo($path->getLastPoint(), $delta)
            || $this->getLastPoint()->isCloseTo($path->getLastPoint(), $delta)
        ;
    }
}
