<?php

namespace App\Service;

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
    ) {
    }

    public function updatePathPoints(Trip $trip, Routing $routing): void
    {
        if (0 === $trip->getSegments()->count()) {
            return;
        }

        /** @var array<int, Path> $paths */
        $paths = $trip->getSegments()
            ->filter(fn (Segment $segment) => \count($segment->getPoints()) >= 2)
            ->map(fn (Segment $segment) => Path::fromSegment($segment))
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

            $routing->setPathPoints($this->geoArithmeticService
                ->getPointsFromPointToPoint(
                    $paths, $startPath, $finishPath, $startPoint, $finishPoint
                )
            );
        } catch (\Exception $e) {
            $this->logger->warning('No route found from $start to $finish point: ' . $e->getMessage());
            // TODO feedback to user
        }
    }

    public function updateCalculatedValues(Routing $routing): void
    {
        if ($routing->pathPointsNotEmpty()) {
            $points = $routing->getPathPoints() ?? [];
            $routing->setDistance(GeoHelper::calculateDistanceFromPoints($points, withElevation: true));
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
            if (abs($diff) < 10) {
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
}
