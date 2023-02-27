<?php

declare(strict_types=1);

namespace Doctrine\ORM\Internal\CommitOrder;

use RuntimeException;

class CycleDetectedException extends RuntimeException
{
}
