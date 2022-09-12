<?php

declare(strict_types=1);

namespace Doctrine\ORM\Exception;

use Exception;

use function implode;

/**
 * This ecxeption gets thrown by the CommitOrderCalculator if a loop was detedted.
 * Usually the CommitOrderCalculator catches this exception and skips an unrequired
 * edge (a nullable reference).
 * In case the loop can't be resolved, this exception is not caught and handeled.
 */
class CommitOrderLoopException extends Exception
{
    public function __construct($vertexHashStack, $vertexHash)
    {
        $loop = implode(', ', $vertexHashStack) . ', ' . $vertexHash;

        parent::__construct("The CommitOrderCalculator found a loop: '{$loop}'");
    }
}
