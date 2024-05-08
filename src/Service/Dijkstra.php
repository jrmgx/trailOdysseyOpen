<?php

namespace App\Service;

use Graphp\Graph\Graph;
use Graphp\Graph\Vertex;

/**
 * Adapted from https://medium.com/@miladev95/dijkstras-graph-algorithm-explained-with-php-example-f55beac842a1.
 */
class Dijkstra
{
    /** @var array<int, Vertex> */
    private array $vertices;
    /** @var array<int, array<int, int>> */
    private array $edgeWeights;

    private const INFINITY = \PHP_INT_MAX;

    public function __construct(Graph $graph)
    {
        foreach ($graph->getVertices() as $vertex) {
            $this->vertices[spl_object_id($vertex->getAttribute('point'))] = $vertex;
        }
        foreach ($graph->getEdges() as $edge) {
            $this->addEdge(
                spl_object_id($edge->getVerticesStart()[0]->getAttribute('point')),
                spl_object_id($edge->getVerticesTarget()[0]->getAttribute('point')),
                $edge->getAttribute('weight', 1)
            );
        }
    }

    private function addEdge(int $startVertexId, int $endVertexId, int $weight): void
    {
        $this->edgeWeights[$startVertexId][$endVertexId] = $weight + 1;
        $this->edgeWeights[$endVertexId][$startVertexId] = $weight + 1;
    }

    /**
     * @return array<int, Vertex>
     */
    public function dijkstra(Vertex $fromVertex, Vertex $toVertex): array
    {
        $fromVertexId = spl_object_id($fromVertex->getAttribute('point'));
        $distances = [];
        $visited = [];
        $path = [];
        foreach ($this->vertices as $id => $vertex) {
            $distances[$id] = self::INFINITY;
            $visited[$id] = false;
            $path[$id] = null;
        }
        $distances[$fromVertexId] = 0;

        foreach ($this->vertices as $_) {
            $currentId = $this->minDistance($distances, $visited);
            $visited[$currentId] = true;

            foreach ($this->vertices as $otherId => $__) {
                if (
                    !$visited[$otherId]
                    && isset($this->edgeWeights[$currentId][$otherId])
                    && 0 !== $this->edgeWeights[$currentId][$otherId]
                    && self::INFINITY !== $distances[$currentId]
                    && $distances[$currentId] + $this->edgeWeights[$currentId][$otherId] < $distances[$otherId]
                ) {
                    $distances[$otherId] = $distances[$currentId] + $this->edgeWeights[$currentId][$otherId];
                    $path[$otherId] = $currentId;
                }
            }
        }

        $hops = [];
        $toVertexId = spl_object_id($toVertex->getAttribute('point'));
        if (empty($path[$toVertexId])) {
            throw new \Exception('Vertex not reachable.');
        }

        $hop = $toVertexId;
        do {
            $hops[] = $this->vertices[$hop];
            $hop = $path[$hop] ?? null;
        } while (null !== $hop);

        return array_reverse($hops);
    }

    /**
     * @param array<int, int>  $distances
     * @param array<int, bool> $visited
     */
    private function minDistance(array $distances, array $visited): int
    {
        $min = self::INFINITY;
        $minIndex = -1;

        foreach ($this->vertices as $id => $vertex) {
            if (!$visited[$id] && $distances[$id] <= $min) {
                $min = $distances[$id];
                $minIndex = $id;
            }
        }

        return $minIndex;
    }
}
