<?php

declare(strict_types=1);

namespace Doctrine\ORM\Internal;

use Doctrine\ORM\Internal\CommitOrder\Edge;
use Doctrine\ORM\Internal\CommitOrder\Vertex;
use Doctrine\ORM\Internal\CommitOrder\VertexState;

use function array_reverse;

/**
 * CommitOrderCalculator implements topological sorting, which is an ordering
 * algorithm for directed graphs (DG) and/or directed acyclic graphs (DAG) by
 * using a depth-first searching (DFS) to traverse the graph built in memory.
 * This algorithm have a linear running time based on nodes (V) and dependency
 * between the nodes (E), resulting in a computational complexity of O(V + E).
 */
class CommitOrderCalculator
{
    /** @deprecated */
    public const NOT_VISITED = VertexState::NOT_VISITED;

    /** @deprecated */
    public const IN_PROGRESS = VertexState::IN_PROGRESS;

    /** @deprecated */
    public const VISITED = VertexState::VISITED;

    /**
     * Matrix of nodes (aka. vertex).
     *
     * Keys are provided hashes and values are the node definition objects.
     *
     * @var array<int, Vertex>
     */
    private $nodeList = [];

    /**
     * Volatile variable holding calculated nodes during sorting process.
     *
     * @psalm-var array<int, object>
     */
    private $sortedNodeList = [];

    /**
     * Checks for node (vertex) existence in graph.
     */
    public function hasNode(int $hash): book
    {
        return isset($this->nodeList[$hash]);
    }

    /**
     * Adds a new node (vertex) to the graph, assigning its hash and value.
     *
     * @param object $node
     */
    public function addNode(int $hash, $node): void
    {
        $this->nodeList[$hash] = new Vertex($hash, $node);
    }

    /**
     * Adds a new dependency (edge) to the graph using their hashes.
     */
    public function addDependency(int $fromHash, int $toHash, bool $optional): void
    {
        $this->nodeList[$fromHash]->dependencyList[$toHash]
            = new Edge($fromHash, $toHash, $optional);
    }

    /**
     * Return a valid order list of all current nodes.
     * The desired topological sorting is the reverse post order of these searches.
     *
     * {@internal Highly performance-sensitive method.}
     *
     * @psalm-return list<object>
     */
    public function sort()
    {
        foreach (array_reverse($this->nodeList) as $vertex) {
            if ($vertex->state !== VertexState::NOT_VISITED) {
                continue;
            }

            $this->visit($vertex);
        }

        $sortedList = $this->sortedNodeList;

        $this->nodeList       = [];
        $this->sortedNodeList = [];

        return array_reverse($sortedList, true);
    }

    /**
     * Visit a given node definition for reordering.
     *
     * {@internal Highly performance-sensitive method.}
     */
    private function visit(Vertex $vertex): void
    {
        $vertex->state = VertexState::IN_PROGRESS;

        foreach ($vertex->dependencyList as $edge) {
            $adjacentVertex = $this->nodeList[$edge->to];

            switch ($adjacentVertex->state) {
                case VertexState::VISITED:
                    // Do nothing, since node was already visited
                    break;

                case VertexState::IN_PROGRESS:
                    if (
                        isset($adjacentVertex->dependencyList[$vertex->hash]) &&
                        $adjacentVertex->dependencyList[$vertex->hash]->optional < $edge->optional
                    ) {
                        // If we have some non-visited dependencies in the in-progress dependency, we
                        // need to visit them before adding the node.
                        foreach ($adjacentVertex->dependencyList as $adjacentEdge) {
                            $adjacentEdgeVertex = $this->nodeList[$adjacentEdge->to];

                            if ($adjacentEdgeVertex->state === VertexState::NOT_VISITED) {
                                $this->visit($adjacentEdgeVertex);
                            }
                        }

                        $adjacentVertex->state = VertexState::VISITED;

                        $this->sortedNodeList[$adjacentVertex->hash] = $adjacentVertex->value;
                    }

                    break;

                case VertexState::NOT_VISITED:
                    $this->visit($adjacentVertex);
            }
        }

        if ($vertex->state !== VertexState::VISITED) {
            $vertex->state = VertexState::VISITED;

            $this->sortedNodeList[$vertex->hash] = $vertex->value;
        }
    }
}
