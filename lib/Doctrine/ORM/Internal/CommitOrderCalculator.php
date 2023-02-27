<?php

declare(strict_types=1);

namespace Doctrine\ORM\Internal;

use Doctrine\ORM\Internal\CommitOrder\CycleDetectedException;
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
    public function hasNode(int $hash): bool
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
     * Returns a topological sort of all nodes. When we have a dependency A->B between two nodes
     * A and B, then A will be listed before B in the result.
     *
     * {@internal Highly performance-sensitive method.}
     *
     * @psalm-return array<int, object>
     */
    public function sort()
    {
        foreach (array_reverse($this->nodeList) as $vertex) {
            if ($vertex->state === VertexState::NOT_VISITED) {
                $this->visit($vertex);
            }
        }

        return array_reverse($this->sortedNodeList, true);
    }

    /**
     * Visit a given node definition for reordering.
     *
     * {@internal Highly performance-sensitive method.}
     */
    private function visit(Vertex $vertex): void
    {
        if ($vertex->state === VertexState::IN_PROGRESS) {
            // This node is already on the current DFS stack. We've found a cycle!
            throw new CycleDetectedException();
        }

        if ($vertex->state === VertexState::VISITED) {
            // We've reached a node that we've already seen, including all
            // other nodes that are reachable from here. We're done here, return.
            return;
        }

        $vertex->state = VertexState::IN_PROGRESS;

        // Continue the DFS downwards the edge list
        foreach ($vertex->dependencyList as $edge) {
            $adjacentVertex = $this->nodeList[$edge->to];

            try {
                $this->visit($adjacentVertex);
            } catch (CycleDetectedException $exception) {
                if ($edge->optional) {
                    // A cycle was found, and $edge is the closest edge while backtracking.
                    // Skip this edge, continue with the next one.
                    continue;
                }

                // We have found a cycle and cannot break it at $edge. Best we can do
                // is to retreat from the current vertex, hoping that somewhere up the
                // stack this can be salvaged.
                $vertex->state = VertexState::NOT_VISITED;

                throw $exception;
            }
        }

        // We have traversed all edges and visited all other nodes reachable from here.
        // So we're done with this vertex as well.

        $vertex->state                       = VertexState::VISITED;
        $this->sortedNodeList[$vertex->hash] = $vertex->value;
    }
}
