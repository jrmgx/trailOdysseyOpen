<?php

namespace App\Service;

use App\Helper\GeoHelper;
use App\Model\Path;
use App\Model\Point;
use Graphp\Graph\Graph;
use Graphp\Graph\Vertex;
use Psr\Log\LoggerInterface;

class GeoArithmeticService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Given a Point, find the closest point on the given paths.
     *
     * @param array<Path> $paths
     *
     * @return array{Point, Path}
     */
    public static function findClosestPointOnPaths(Point $candidatePoint, array $paths): array
    {
        $closestPoint = null;
        $closestPath = null;
        $closestDistance = null;

        foreach ($paths as $path) {
            foreach ($path->getPoints() as $point) {
                $distance = GeoHelper::calculateDistance($candidatePoint, $point);

                if (null === $closestDistance || $distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closestPoint = $point;
                    $closestPath = $path;
                }
            }
        }

        if (null === $closestPoint || null === $closestPath) {
            throw new \Exception('No closest point found');
        }

        return [$closestPoint, $closestPath];
    }

    /**
     * @param array<Path> $paths
     *
     * @return ?array<Point>
     */
    public function getPointsFromPointToPoint(
        array $paths,
        Path $startPath, Path $endPath,
        Point $startPoint, Point $endPoint,
    ): ?array {
        if (!$startPath->equals($endPath)) {
            $paths = $this->findPaths($paths, $startPath, $endPath, $startPoint, $endPoint);
        } else {
            $paths = [$startPath];
        }
        $this->orientPaths($paths, $startPoint, $endPoint);

        $points = [];
        $startPointFound = false;
        foreach ($paths as $path) {
            foreach ($path->getPoints() as $point) {
                if ($point->equals($startPoint)) {
                    $startPointFound = true;
                }

                if ($startPointFound) {
                    $points[] = $point;
                }

                if ($point->equals($endPoint) && $startPointFound) {
                    return $points;
                }
            }
        }

        throw new \Exception('Did not find a way from path to path.');
    }

    /**
     * @param array<Path> $paths
     *
     * @return array{0: Graph, 1: Vertex, 2: Vertex}
     */
    private function buildGraph(array $paths, Path $pathStart, Path $pathEnd, Point $pointFrom, Point $pointTo): array
    {
        $graph = new Graph();

        // Build vertices
        /** @var array<int, Vertex> $vertices */
        $vertices = [];
        $vertices[spl_object_id($pointFrom)] = $vertexFrom = $this->createVertex($graph, 'From', $pointFrom, $pathStart);
        $vertices[spl_object_id($pointTo)] = $vertexTo = $this->createVertex($graph, 'To', $pointTo, $pathEnd);
        foreach ($paths as $path) {
            $a = $path->getFirstPoint();
            if ($a !== $pointFrom && $a !== $pointTo) {
                $vertices[spl_object_id($a)] = $this->createVertex($graph, $path->getName() . ' Start', $a, $path);
            }
            $b = $path->getLastPoint();
            if ($b !== $pointFrom && $b !== $pointTo) {
                $vertices[spl_object_id($b)] = $this->createVertex($graph, $path->getName() . ' End', $b, $path);
            }
        }

        // Connect close vertices
        $alreadyConnected = [];
        foreach ($vertices as $vertex) {
            /** @var Point $a */
            $a = $vertex->getAttribute('point');
            foreach ($vertices as $vertexInner) {
                /** @var Point $b */
                $b = $vertexInner->getAttribute('point');
                if ($b !== $a && $b->isCloseTo($a, 80)
                    && !( // Not already connected
                        isset($alreadyConnected[spl_object_id($a) . '-' . spl_object_id($b)])
                        || isset($alreadyConnected[spl_object_id($b) . '-' . spl_object_id($a)])
                    )
                ) {
                    $alreadyConnected[spl_object_id($a) . '-' . spl_object_id($b)] = true;
                    $alreadyConnected[spl_object_id($b) . '-' . spl_object_id($a)] = true;
                    $this->createEdge($graph, $vertices, $a, $b);
                }
            }
        }

        // Connect paths vertices
        // Manual connection of From Point and Start Path in both ways
        $this->createEdge($graph, $vertices, $pathStart->getFirstPoint(), $pointFrom);
        $this->createEdge($graph, $vertices, $pathStart->getLastPoint(), $pointFrom);
        // Manual connection of To Point and End Path in both ways
        $this->createEdge($graph, $vertices, $pathEnd->getFirstPoint(), $pointTo);
        $this->createEdge($graph, $vertices, $pathEnd->getLastPoint(), $pointTo);
        // Other paths
        $paths = array_filter($paths, fn (Path $p) => $p !== $pathStart && $p !== $pathEnd);
        foreach ($paths as $path) {
            $a = $path->getFirstPoint();
            $b = $path->getLastPoint();
            $this->createEdge($graph, $vertices, $a, $b);
        }

        return [$graph, $vertexFrom, $vertexTo];
    }

    /**
     * @param array<int, Vertex> $vertices
     */
    private function createEdge(Graph $graph, array $vertices, Point $a, Point $b): void
    {
        $this->d(
            'Edge between ' . $vertices[spl_object_id($a)]->getAttribute('id') .
            ' and ' . $vertices[spl_object_id($b)]->getAttribute('id')
        );
        $graph->createEdgeUndirected($vertices[spl_object_id($a)], $vertices[spl_object_id($b)], [
            'weight' => GeoHelper::calculateDistance($a, $b)]
        );
    }

    private function createVertex(Graph $graph, string $id, Point $point, Path $path): Vertex
    {
        $id .= ' ' . spl_object_id($point);
        $this->d('Created vertex for ' . $id);

        return $graph->createVertex([
            'id' => $id,
            'point' => $point,
            'path' => $path,
        ]);
    }

    private function d(string $message): void
    {
        // dump($message);
        $this->logger->debug($message);
    }

    /**
     * @param array<Path> $paths
     *
     * @return array<Path>
     */
    private function findPaths(array $paths, Path $startPath, Path $endPath, Point $startPoint, Point $endPoint): array
    {
        // Aggressive optimization: we will only include $paths that cross the bounding box of $start + $endPath
        $boundingBox = GeoHelper::getBoundingBox(new Path(array_merge($startPath->getPoints(), $endPath->getPoints())));
        // To be safe we enlarge our bounding box times 3, in specific cases it could not even be enough
        $boundingBox = array_map(fn (string $value) => (float) $value, $boundingBox);
        $bbLatSize = $boundingBox['maxLat'] - $boundingBox['minLat'];
        $bbLonSize = $boundingBox['maxLon'] - $boundingBox['minLon'];
        $boundingBox['maxLat'] = (string) ($boundingBox['maxLat'] + $bbLatSize);
        $boundingBox['minLat'] = (string) ($boundingBox['minLat'] - $bbLatSize);
        $boundingBox['maxLon'] = (string) ($boundingBox['maxLon'] + $bbLonSize);
        $boundingBox['minLon'] = (string) ($boundingBox['minLon'] - $bbLonSize);

        $paths = array_filter($paths, fn (Path $otherPath) => GeoHelper::doBoundingBoxesOverlap(
            $boundingBox,
            GeoHelper::getBoundingBox($otherPath)
        ));

        $final = [];
        try {
            [$graph, $vertexStart, $vertexEnd] = $this->buildGraph($paths, $startPath, $endPath, $startPoint, $endPoint);
            // This part is to allow better debugging
            // You must do a `apt install graphviz` into the builder first
            // $graphviz = new \Graphp\GraphViz\GraphViz();
            // $filename = $graphviz->createImageFile($graph);
            // copy($filename, __DIR__ . '/../../var/' . time() . '.png');

            $dijkstra = new Dijkstra($graph);
            $vertices = $dijkstra->dijkstra($vertexStart, $vertexEnd);
            foreach ($vertices as $vertex) {
                /** @var Path $path */
                $path = $vertex->getAttribute('path');
                if (!\in_array($path, $final, true)) {
                    $final[] = $path;
                }
            }
        } catch (\Throwable $throwable) {
            $this->logger->error('Error when finding path', ['exception' => $throwable]);
        }

        return $final;
    }

    /**
     * Given multiple paths in continuous order, update their direction to match each others.
     *
     * @param array<Path> $paths
     */
    private function orientPaths(array $paths, Point $startPoint, Point $endPoint): void
    {
        if (0 === \count($paths)) {
            return;
        }

        if (1 === \count($paths)) {
            $path = $paths[0];
            foreach ($path->getPoints() as $point) {
                if ($point->equals($startPoint)) {
                    return;
                }
                if ($point->equals($endPoint)) {
                    $path->reverse();

                    return;
                }
            }
        }

        $currentPath = $paths[0];
        if (!$currentPath->containPoint($startPoint)) {
            throw new \Exception('First path does not contain first point.');
        }

        for ($i = 0; $i < \count($paths) - 1; ++$i) {
            $currentPath = $paths[$i];
            $nextPath = $paths[$i + 1];

            /* A ====current==== B    C ====next==== D */
            $A = $currentPath->getFirstPoint();
            $B = $currentPath->getLastPoint();
            $C = $nextPath->getFirstPoint();
            $D = $nextPath->getLastPoint();

            $dist1 = GeoHelper::calculateDistance($A, $C); // A close to C => reverse current
            $dist2 = GeoHelper::calculateDistance($B, $D); // B close to D => reverse next
            $dist3 = GeoHelper::calculateDistance($A, $D); // A close to D => reverse both
            $dist4 = GeoHelper::calculateDistance($B, $C); // B close to C => reverse none
            switch (min($dist1, $dist2, $dist3, $dist4)) {
                case $dist1:
                    $currentPath->reverse();
                    break;
                case $dist2:
                    $nextPath->reverse();
                    break;
                case $dist3:
                    $currentPath->reverse();
                    $nextPath->reverse();
                    break;
                case $dist4:
                    break;
            }
        }
    }
}
