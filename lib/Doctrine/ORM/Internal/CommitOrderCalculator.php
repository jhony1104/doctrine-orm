<?php

declare(strict_types=1);

namespace Doctrine\ORM\Internal;

use Doctrine\ORM\Exception\CommitOrderLoopException;
use stdClass;

use function array_map;
use function array_merge;
use function array_reverse;
use function in_array;

/**
 * CommitOrderCalculator implements topological sorting, which is an ordering
 * algorithm for directed graphs (DG) and/or directed acyclic graphs (DAG) by
 * using a depth-first searching (DFS) to traverse the graph built in memory.
 * This algorithm have a linear running time based on nodes (V) and dependency
 * between the nodes (E), resulting in a computational complexity of O(V + E).
 */
class CommitOrderCalculator
{
    /**
     * Matrix of nodes (aka. vertex).
     * Keys are provided hashes and values are the node definition objects.
     *
     * The node state definition contains the following properties:
     *
     * - <b>state</b> (integer)
     * Whether the node is NOT_VISITED or IN_PROGRESS
     *
     * - <b>value</b> (object)
     * Actual node value
     *
     * - <b>dependencyList</b> (array<string>)
     * Map of node dependencies defined as hashes.
     *
     * @var array<stdClass>
     */
    private $nodeList = [];

    /**
     * Checks for node (vertex) existence in graph.
     *
     * @param string $hash
     *
     * @return bool
     */
    public function hasNode($hash)
    {
        return isset($this->nodeList[$hash]);
    }

    /**
     * Adds a new node (vertex) to the graph, assigning its hash and value.
     *
     * @param string $hash
     * @param object $node
     *
     * @return void
     */
    public function addNode($hash, $node)
    {
        $vertex = new stdClass();

        $vertex->hash           = $hash;
        $vertex->value          = $node;
        $vertex->dependencyList = [];

        $this->nodeList[$hash] = $vertex;
    }

    /**
     * Adds a new dependency (edge) to the graph using their hashes.
     *
     * @param string $fromHash
     * @param string $toHash
     * @param bool   $required
     *
     * @return void
     */
    public function addDependency($fromHash, $toHash, $required)
    {
        $vertex = $this->nodeList[$fromHash];

        // don't replace required edge with optional one
        if (isset($vertex->dependencyList[$toHash]) && $vertex->dependencyList[$toHash]->required) {
            return;
        }

        $edge = new stdClass();

        $edge->from     = $fromHash;
        $edge->to       = $toHash;
        $edge->required = $required;

        $vertex->dependencyList[$toHash] = $edge;
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
        $visited = [];

        foreach ($this->nodeList as $vertex) {
            $visited = $this->visit($vertex, $visited, []);
        }

        $sortedList = array_map(function ($hash) {
            return $this->nodeList[$hash]->value;
        }, $visited);

        $this->nodeList = [];

        return array_reverse($sortedList);
    }

    /**
     * Visit a given node definition for reordering.
     *
     * {@internal Highly performance-sensitive method.}
     */
    private function visit(stdClass $vertex, $visited, $parents): array
    {
        // if loop is encountered abandon path by thowning exception
        if (in_array($vertex->hash, $parents)) {
            throw new CommitOrderLoopException($parents, $vertex->hash);
        }

        // if already visited nothing needs to be done
        if (in_array($vertex->hash, $visited)) {
            return $visited;
        }

        foreach ($vertex->dependencyList as $toHash => $edge) {
            // skip self references (node is currently visited)
            if ($vertex->hash === $toHash) {
                continue;
            }

            // if edge is required don't catch loops
            if ($edge->required) {
                $visited = $this->visit($this->nodeList[$toHash], $visited, array_merge($parents, [$vertex->hash]));
            } else {
                try {
                    $visited = $this->visit($this->nodeList[$toHash], $visited, array_merge($parents, [$vertex->hash]));
                } catch (CommitOrderLoopException $ex) {
                }
            }
        }

        return array_merge($visited, [$vertex->hash]);
    }
}
