<?php

namespace App\Service;

use App\Entity\Segment;
use App\Helper\GeoHelper;
use App\Model\Path;
use App\Model\Point;
use Doctrine\ORM\EntityManagerInterface;

class SegmentIntersectionService
{
    private const SAME_POINT_DELTA_METERS = 2;

    private const EDGE_ENDPOINT_EPSILON = 1e-6;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Find all intersections between segment pairs, split each segment at its intersection points,
     * persist new sub-segments and remove originals.
     * Uses multiple rounds: split at one intersection per segment per round, then repeat.
     *
     * @param iterable<Segment> $segments
     *
     * @return array<Segment> The new sub-segments (already persisted)
     */
    public function splitSegmentsAtIntersections(iterable $segments): array
    {
        $segments = [...$segments];
        if (0 === \count($segments)) {
            return [];
        }

        $allSegments = $segments;
        $round = 0;
        $maxRounds = 100;

        while ($round < $maxRounds) {
            $split = $this->splitOneRound($allSegments);
            if (0 === \count($split['splits'])) {
                break;
            }
            $allSegments = $split['segments'];
            ++$round;
        }

        return $allSegments;
    }

    /**
     * One round: find intersections, split each segment at most once (at the intersection closest to start).
     *
     * @param array<int, Segment> $segments
     *
     * @return array{segments: array<Segment>, splits: array<int>}
     */
    private function splitOneRound(array $segments): array
    {
        $segments = array_values($segments);
        $count = \count($segments);
        /** @var array<int, list<array{index: int, point: Point, distanceFromStart: int, atVertex: bool}>> $splitPointsBySegment */
        $splitPointsBySegment = array_fill_keys(array_keys($segments), []);
        for ($i = 0; $i < $count; ++$i) {
            $a = $segments[$i];
            $aPoints = $a->getPoints();
            if (\count($aPoints) < 2) {
                continue;
            }
            try {
                $aBbox = GeoHelper::getBoundingBox(Path::fromSegment($a));
            } catch (\Throwable) {
                continue;
            }

            for ($j = $i + 1; $j < $count; ++$j) {
                $b = $segments[$j];
                $bPoints = $b->getPoints();
                if (\count($bPoints) < 2) {
                    continue;
                }
                try {
                    $bBbox = GeoHelper::getBoundingBox(Path::fromSegment($b));
                } catch (\Throwable) {
                    continue;
                }
                if (!GeoHelper::doBoundingBoxesOverlap($aBbox, $bBbox)) {
                    continue;
                }

                for ($ai = 0; $ai < \count($aPoints) - 1; ++$ai) {
                    for ($bj = 0; $bj < \count($bPoints) - 1; ++$bj) {
                        $result = GeoHelper::lineSegmentIntersectionWithT($aPoints[$ai], $aPoints[$ai + 1], $bPoints[$bj], $bPoints[$bj + 1]);
                        if (null === $result) {
                            continue;
                        }
                        [$intersection, $t, $s] = $result;
                        $interiorA = $t > self::EDGE_ENDPOINT_EPSILON && $t < 1 - self::EDGE_ENDPOINT_EPSILON;
                        $interiorB = $s > self::EDGE_ENDPOINT_EPSILON && $s < 1 - self::EDGE_ENDPOINT_EPSILON;
                        $indexA = $interiorA ? $ai + 1 : ($t >= 0.5 ? $ai + 1 : $ai);
                        $indexB = $interiorB ? $bj + 1 : ($s >= 0.5 ? $bj + 1 : $bj);
                        $validSplitA = $indexA >= 1 && $indexA <= \count($aPoints) - 2;
                        $validSplitB = $indexB >= 1 && $indexB <= \count($bPoints) - 2;
                        $atVertexA = !$interiorA;
                        $atVertexB = !$interiorB;
                        $addA = ($interiorA || ($atVertexA && $validSplitA)) && !$this->intersectionAlreadyCollected($intersection, $splitPointsBySegment[$i]);
                        $addB = ($interiorB || ($atVertexB && $validSplitB)) && !$this->intersectionAlreadyCollected($intersection, $splitPointsBySegment[$j]);
                        $pointA = $atVertexA ? ($t >= 0.5 ? $aPoints[$ai + 1] : $aPoints[$ai]) : $intersection;
                        $pointB = $atVertexB ? ($s >= 0.5 ? $bPoints[$bj + 1] : $bPoints[$bj]) : $intersection;
                        $distFromStartA = GeoHelper::calculateDistanceFast($aPoints[0], $pointA);
                        if ($addA) {
                            $splitPointsBySegment[$i][] = ['index' => $indexA, 'point' => $pointA, 'distanceFromStart' => $distFromStartA, 'atVertex' => $atVertexA];
                        }
                        $distFromStartB = GeoHelper::calculateDistanceFast($bPoints[0], $pointB);
                        if ($addB) {
                            $splitPointsBySegment[$j][] = ['index' => $indexB, 'point' => $pointB, 'distanceFromStart' => $distFromStartB, 'atVertex' => $atVertexB];
                        }
                    }
                }
            }
        }

        $newSegments = [];
        $splits = [];

        foreach ($segments as $idx => $segment) {
            $insertions = $splitPointsBySegment[$idx];
            if (0 === \count($insertions)) {
                $newSegments[] = $segment;
                continue;
            }

            try {
                $points = $segment->getPoints();
                $best = $insertions[0];
                foreach ($insertions as $ins) {
                    if ($ins['distanceFromStart'] < $best['distanceFromStart']) {
                        $best = $ins;
                    }
                }

                $insertAt = $best['index'];
                if (!$best['atVertex']) {
                    array_splice($points, $insertAt, 0, [$best['point']]);
                }

                $trip = $segment->getTrip();
                $user = $segment->getUser();
                $baseName = $segment->getName();

                $this->entityManager->remove($segment);

                $sub1 = new Segment();
                $sub1->setPoints(\array_slice($points, 0, $insertAt + 1));
                $sub1->setName($baseName);
                $sub1->setTrip($trip);
                $sub1->setUser($user);
                $this->entityManager->persist($sub1);
                $newSegments[] = $sub1;

                $sub2 = new Segment();
                $sub2->setPoints(\array_slice($points, $insertAt));
                $sub2->setName($baseName);
                $sub2->setTrip($trip);
                $sub2->setUser($user);
                $this->entityManager->persist($sub2);
                $newSegments[] = $sub2;

                $splits[] = $idx;
            } catch (\Throwable) {
                $this->entityManager->persist($segment);
                $newSegments[] = $segment;
            }
        }

        return ['segments' => $newSegments, 'splits' => $splits];
    }

    /**
     * @param list<array{index: int, point: Point, distanceFromStart: int, atVertex: bool}> $collected
     */
    private function intersectionAlreadyCollected(Point $intersection, array $collected): bool
    {
        foreach ($collected as $item) {
            if ($intersection->isCloseTo($item['point'], self::SAME_POINT_DELTA_METERS)) {
                return true;
            }
        }

        return false;
    }
}
