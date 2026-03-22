<?php

namespace App\Service;

use App\Entity\Interest;
use App\Entity\Routing;
use App\Entity\Segment;
use App\Entity\Trip;
use App\Helper\GeoHelper;
use App\Model\Path;
use App\Model\Point;
use Psr\Log\LoggerInterface;

class RoutingService
{
    public function __construct(
        private readonly GeoArithmeticService $geoArithmeticService,
        private readonly LoggerInterface $logger,
        private readonly TripService $tripService,
    ) {
    }

    public function updatePathPoints(Trip $trip, Routing $routing): void
    {
        if (0 === $trip->getSegments()->count()) {
            return;
        }

        /** @var array<int, Path> $paths */
        $paths = $trip->getSegments()
            ->filter(static fn (Segment $segment) => \count($segment->getPoints()) >= 2)
            ->map(static fn (Segment $segment) => Path::fromSegment($segment))
            ->toArray()
        ;

        $startStage = $routing->getStartStage();
        $finishStage = $routing->getFinishStage();

        try {
            [$startPoint, $startPath] = GeoArithmeticService::findClosestPointOnPaths(
                $startStage->getPoint()->toPoint(), $paths
            );
            [$finishPoint, $finishPath] = GeoArithmeticService::findClosestPointOnPaths(
                $finishStage->getPoint()->toPoint(), $paths
            );

            $checkpointInterests = $this->checkpointInterestsForRouting($trip, $routing);
            if (\count($checkpointInterests) > 0) {
                /** @var array<int, array<int, Point>> $segments */
                $segments = [];
                $currentPoint = $startPoint;
                $currentPath = $startPath;
                foreach ($checkpointInterests as $interest) {
                    [$viaPoint, $viaPath] = GeoArithmeticService::findClosestPointOnPaths(
                        $interest->getPoint()->toPoint(), $paths
                    );
                    $leg = $this->geoArithmeticService->getPointsFromPointToPoint(
                        $paths, $currentPath, $viaPath, $currentPoint, $viaPoint
                    );
                    \assert(null !== $leg);
                    $segments[] = $leg;
                    $currentPoint = $viaPoint;
                    $currentPath = $viaPath;
                }
                $lastLeg = $this->geoArithmeticService->getPointsFromPointToPoint(
                    $paths, $currentPath, $finishPath, $currentPoint, $finishPoint
                );
                \assert(null !== $lastLeg);
                $segments[] = $lastLeg;
                $routing->setPathPoints($this->mergePathSegments($segments));
            } else {
                $routing->setPathPoints($this->geoArithmeticService
                    ->getPointsFromPointToPoint(
                        $paths, $startPath, $finishPath, $startPoint, $finishPoint
                    )
                );
            }
        } catch (\Exception $e) {
            $this->logger->warning('No route found from $start to $finish point: ' . $e->getMessage());
            // TODO feedback to user
        }
    }

    /**
     * Recalculates every leg: checkpoint assignment uses interest and stage datetimes.
     */
    public function refreshAllPathPointsForTrip(Trip $trip): void
    {
        foreach ($this->tripService->calculateRoutings($trip) as $routing) {
            $routing->setPathPoints(null);
            $this->updatePathPoints($trip, $routing);
            $this->updateCalculatedValues($routing);
        }
    }

    public function updateCalculatedValues(Routing $routing): void
    {
        if ($routing->pathPointsNotEmpty()) {
            $points = $routing->getPathPoints() ?? [];
            $routing->setDistance(GeoHelper::calculateDistanceFromPoints($points));
            [$positive, $negative] = $this->calculateElevations($points);
            $routing->setElevationPositive($positive);
            $routing->setElevationNegative($negative);
            $routing->setAsTheCrowFly(false);
        } else {
            $routing->setDistance(GeoHelper::calculateDistance(
                $routing->getStartStage()->getPoint()->toPoint(),
                $routing->getFinishStage()->getPoint()->toPoint()
            ));
            $routing->setElevationPositive(null);
            $routing->setElevationNegative(null);
            $routing->setAsTheCrowFly(true);
        }
    }

    /**
     * https://www.gpsvisualizer.com/tutorials/elevation_gain.html
     * Horizontal smoothing is 20m
     * Vertical smoothing is 20m
     * Those value comes from tests with multiples path and external elevation sources.
     *
     * @param array<Point> $points
     *
     * @return array{int, int}
     */
    private function calculateElevations(array $points): array
    {
        $count = \count($points);
        if ($count < 2) {
            return [0, 0];
        }
        $positive = 0;
        $negative = 0;
        $previousElevation = (int) $points[0]->el; // (int) '' = (int) null = 0
        $previousPoint = $points[0];
        for ($i = 1; $i < $count; ++$i) {
            $currentElevation = (int) $points[$i]->el;
            $currentPoint = $points[$i];

            // Horizontal smoothing
            if (GeoHelper::calculateDistanceFast($currentPoint, $previousPoint) < 20) {
                continue;
            }

            $diff = $previousElevation - $currentElevation;

            // Vertical smoothing
            if (abs($diff) < 20) {
                continue;
            }

            if ($diff < 0) {
                $positive -= $diff;
            } else {
                $negative += $diff;
            }
            $previousElevation = $currentElevation;
            $previousPoint = $points[$i];
        }

        return [$positive, $negative];
    }

    /**
     * @return array<Interest>
     */
    private function checkpointInterestsForRouting(Trip $trip, Routing $routing): array
    {
        $candidates = [];
        foreach ($trip->getInterests() as $interest) {
            if (!$interest->isCheckpoint()) {
                continue;
            }
            $assigned = $this->routingForCheckpointInterestByDate($trip, $interest);
            if ($assigned === $routing) {
                $candidates[] = $interest;
            }
        }

        usort($candidates, static function (Interest $a, Interest $b): int {
            $byDate = $a->getArrivingAt() <=> $b->getArrivingAt();
            if (0 !== $byDate) {
                return $byDate;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        return $candidates;
    }

    /**
     * Legs are consecutive in trip order. Inclusive time window per leg [startStage, finishStage];
     * when several legs match (e.g. shared boundary instant), the last leg along the trip wins.
     */
    private function routingForCheckpointInterestByDate(Trip $trip, Interest $interest): ?Routing
    {
        $routings = $this->tripService->calculateRoutings($trip);
        $interestAt = $interest->getArrivingAt();
        $match = null;
        foreach ($routings as $candidateRouting) {
            $startAt = $candidateRouting->getStartStage()->getArrivingAt();
            $finishAt = $candidateRouting->getFinishStage()->getArrivingAt();
            if ($interestAt >= $startAt && $interestAt <= $finishAt) {
                $match = $candidateRouting;
            }
        }

        return $match;
    }

    /**
     * @param array<int, array<int, Point>> $segments
     *
     * @return array<int, Point>
     */
    private function mergePathSegments(array $segments): array
    {
        $merged = [];
        foreach ($segments as $segment) {
            foreach ($segment as $point) {
                $last = $merged[\count($merged) - 1] ?? null;
                if ($last instanceof Point && $point->equalsWithoutElevation($last)) {
                    continue;
                }
                $merged[] = $point;
            }
        }

        return $merged;
    }
}
