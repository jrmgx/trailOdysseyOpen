<?php

namespace App\Model;

class Extra
{
    public function __construct(
        private Point $startPoint,
        private Point $finishPoint,
        private int $distance,
    ) {
    }

    public function setStartPoint(Point $startPoint): self
    {
        $this->startPoint = $startPoint;

        return $this;
    }

    public function getStartPoint(): Point
    {
        return $this->startPoint;
    }

    public function getFinishPoint(): Point
    {
        return $this->finishPoint;
    }

    public function setFinishPoint(Point $finishPoint): self
    {
        $this->finishPoint = $finishPoint;

        return $this;
    }

    public function getDistance(): int
    {
        return $this->distance;
    }

    public function setDistance(int $distance): self
    {
        $this->distance = $distance;

        return $this;
    }
}
