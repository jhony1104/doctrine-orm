<?php

declare(strict_types=1);

namespace Doctrine\ORM\Internal\CommitOrder;

/** @internal */
final class Vertex
{
    /**
     * @var int
     * @readonly
     */
    public $hash;

    /**
     * @var int
     * @psalm-var VertexState::*
     */
    public $state = VertexState::NOT_VISITED;

    /**
     * @var object
     * @readonly
     */
    public $value;

    /** @var array<int, Edge> */
    public $dependencyList = [];

    /** @param object $value */
    public function __construct(int $hash, $value)
    {
        $this->hash  = $hash;
        $this->value = $value;
    }
}
