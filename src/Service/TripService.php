<?php

namespace App\Service;

use App\Entity\Routing;
use App\Entity\Stage;
use App\Entity\Trip;
use App\Helper\GeoHelper;
use App\Model\Extra;
use App\Model\Path;
use App\Model\Point;
use App\Repository\StageRepository;

class TripService
{
    public function __construct(
        private readonly StageRepository $stageRepository,
    ) {
    }

    /**
     * @return array{0: array<Stage|Routing>, 1: array<Stage>, 2: array<Routing>, 3: array<Extra>, 4: int}
     */
    public function calculateResults(Trip $trip): array
    {
        /** @var array<Stage|Routing> $results */
        $results = [];
        /** @var array<Stage> $stages */
        $stages = [];
        /** @var array<Routing> $routings */
        $routings = [];

        $currentStage = $this->stageRepository->findFirstStage($trip);
        $totalDistance = 0;
        $number = 0;
        while ($currentStage) {
            $currentStage->setSymbol($number > 0 ? (string) $number : '📍');
            ++$number;

            $results[] = $currentStage;
            $stages[] = $currentStage;
            $routingOut = $currentStage->getRoutingOut();

            if ($routingOut) {
                $totalDistance += $routingOut->getDistance() ?? 0;
                $results[] = $routingOut;
                $routings[] = $routingOut;
                $currentStage = $routingOut->getFinishStage();
            } else {
                $currentStage->setSymbol('🏁');
                $currentStage = null;
            }
        }

        /** @var array<Extra> $extras */
        $extras = array_filter(array_map(
            fn (Stage $stage) => $this->getExtra($stage), $stages)
        );

        return [$results, $stages, $routings, $extras, $totalDistance];
    }

    /**
     * @return array<int, Routing>
     */
    public function calculateRoutings(Trip $trip): array
    {
        /** @var array<Routing> $routings */
        $routings = [];

        $currentStage = $this->stageRepository->findFirstStage($trip);
        while ($currentStage) {
            $routingOut = $currentStage->getRoutingOut();

            if ($routingOut) {
                $routings[] = $routingOut;
                $currentStage = $routingOut->getFinishStage();
            } else {
                $currentStage = null;
            }
        }

        return $routings;
    }

    public function calculateTotalDistance(Trip $trip): int
    {
        $currentStage = $this->stageRepository->findFirstStage($trip);
        $totalDistance = 0;
        while ($currentStage) {
            $routingOut = $currentStage->getRoutingOut();
            if ($routingOut) {
                $totalDistance += $routingOut->getDistance() ?? 0;
                $currentStage = $routingOut->getFinishStage();
            } else {
                $currentStage = null;
            }
        }

        return $totalDistance;
    }

    /**
     * For each stage where the Point is supposed to be on a path but the distance from it
     * is > 300m then add an Extra to show that to the user.
     * It will not count in the routing distance, neither in the total distance.
     * FIXME this is non optimal code (but that's fine for now).
     */
    private function getExtra(Stage $stage): ?Extra
    {
        try {
            $routing = $stage->getRoutingOut() ?? $stage->getRoutingIn();
            if ($routing && $routing->pathPointsNotEmpty()) {
                $points = $routing->getPathPoints() ?? [];
                if (\count($points) < 2) {
                    return null;
                }
                $stagePoint = $stage->getPoint()->toPoint();
                /** @var array<Point> $points */
                $points = [$points[0], $points[\count($points) - 1]];
                $path = new Path($points);
                $closest = GeoArithmeticService::findClosestPointOnPaths($stagePoint, [$path]);
                $distance = GeoHelper::calculateDistance($stagePoint, $closest[0]);
                if ($distance > 300) {
                    $extra = new Extra($stagePoint, $closest[0], $distance);
                    $stage->setExtra($extra);

                    return $extra;
                }
            }
        } catch (\Exception) {
            // Error, returns null
        }

        return null;
    }
}
