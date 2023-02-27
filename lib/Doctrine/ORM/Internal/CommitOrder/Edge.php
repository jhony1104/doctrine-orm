<?php

declare(strict_types=1);

namespace Doctrine\ORM\Internal\CommitOrder;

/** @internal */
final class Edge
{
    /**
     * @var int
     * @readonly
     */
    public $from;

    /**
     * @var int
     * @readonly
     */
    public $to;

    /**
     * @var bool
     * @readonly
     */
    public $optional;

    public function __construct(int $from, int $to, bool $optional)
    {
        $this->from     = $from;
        $this->to       = $to;
        $this->optional = $optional;
    }
}
