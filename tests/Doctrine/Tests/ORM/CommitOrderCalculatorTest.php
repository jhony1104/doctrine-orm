<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM;

use Doctrine\ORM\Internal\CommitOrder\CycleDetectedException;
use Doctrine\ORM\Internal\CommitOrderCalculator;
use Doctrine\Tests\OrmTestCase;
use function array_map;
use function array_values;
use function spl_object_id;

/**
 * Tests of the commit order calculation.
 *
 * IMPORTANT: When writing tests here consider that a lot of graph constellations
 * can have many valid orderings, so you may want to build a graph that has only
 * 1 valid order to simplify your tests.
 */
class CommitOrderCalculatorTest extends OrmTestCase
{
    /** @var array<string, Node> */
    private $nodes = [];

    /** @var CommitOrderCalculator */
    private $_calc;

    protected function setUp(): void
    {
        $this->_calc = new CommitOrderCalculator();
    }

    public function testSimpleOrdering(): void
    {
        $this->addNodes('C', 'B', 'A', 'E');

        $this->addDependency('A', 'B');
        $this->addDependency('B', 'C');
        $this->addDependency('E', 'A');

        // There is only 1 valid ordering for this constellation
        self::assertSame(['E', 'A', 'B', 'C'], $this->computeResult());
    }

    public function testSkipOptionalEdgeToBreakCycle(): void
    {
        $this->addNodes('A', 'B');

        $this->addDependency('A', 'B', true);
        $this->addDependency('B', 'A', false);

        self::assertSame(['B', 'A'], $this->computeResult());
    }

    public function testBreakCycleByBacktracking(): void
    {
        $this->addNodes('A', 'B', 'C', 'D');

        $this->addDependency('A', 'B');
        $this->addDependency('B', 'C', true);
        $this->addDependency('C', 'D');
        $this->addDependency('D', 'A'); // closes the cycle

        // We can only break B -> C, so the result must be C -> D -> A -> B
        self::assertSame(['C', 'D', 'A', 'B'], $this->computeResult());
    }

    public function testCycleRemovedByEliminatingLastOptionalEdge(): void
    {
        // The cycle-breaking algorithm is currently very naive. It breaks the cycle
        // at the last optional edge while it backtracks. In this example, we might
        // get away with one extra update if we'd break A->B; instead, we break up
        // B->C and B->D.

        $this->addNodes('A', 'B', 'C', 'D');

        $this->addDependency('A', 'B', true);
        $this->addDependency('B', 'C', true);
        $this->addDependency('C', 'A');
        $this->addDependency('B', 'D', true);
        $this->addDependency('D', 'A');

        self::assertSame(['C', 'D', 'A', 'B'], $this->computeResult());
    }

    public function testGH7180Example(): void
    {
        // Example given in https://github.com/doctrine/orm/pull/7180#issuecomment-381341943

        $this->addNodes('E', 'F', 'D', 'G');

        $this->addDependency('D', 'G');
        $this->addDependency('D', 'F', true);
        $this->addDependency('F', 'E');
        $this->addDependency('E', 'D');

        self::assertSame(['F', 'E', 'D', 'G'], $this->computeResult());
    }

    public function testCommitOrderingFromGH7259Test(): void
    {
        // this test corresponds to the GH7259Test::testPersistFileBeforeVersion functional test
        $this->addNodes('A', 'B', 'C', 'D');

        $this->addDependency('D', 'A');
        $this->addDependency('A', 'B');
        $this->addDependency('D', 'C');
        $this->addDependency('A', 'D', true);

        // There is only multiple valid ordering for this constellation, but
        // the D -> A -> B ordering is important to break the cycle
        // on the nullable link.
        $correctOrders = [
            ['D', 'A', 'B', 'C'],
            ['D', 'A', 'C', 'B'],
            ['D', 'C', 'A', 'B'],
        ];

        self::assertContains($this->computeResult(), $correctOrders);
    }

    public function testCommitOrderingFromGH8349Case1Test()
    {
        $this->addNodes('A', 'B', 'C', 'D');

        $this->addDependency('D', 'A');
        $this->addDependency('A', 'B', true);
        $this->addDependency('B', 'D', true);
        $this->addDependency('B', 'C', true);
        $this->addDependency('C', 'D', true);

        // Many orderings are possible here, but the bottom line is D must be before A (it's the only hard requirement).
        $result = $this->computeResult();

        $indexA = array_search('A', $result, true);
        $indexD = array_search('D', $result, true);
        self::assertTrue($indexD < $indexA);
    }

    public function testCommitOrderingFromGH8349Case2Test()
    {
        $this->addNodes('A', 'B');

        $this->addDependency('B', 'A');
        $this->addDependency('B', 'A', true); // interesting: We have two edges in that direction
        $this->addDependency('A', 'B', true);

        // The B -> A requirement determines the result here
        self::assertSame(['B', 'A'], $this->computeResult());
    }

    public function testNodesMaintainOrderWhenNoDepencency(): void
    {
        $this->addNodes('A', 'B', 'C');

        // Nodes that are not constrained by dependencies shall maintain the order
        // in which they were added
        self::assertSame(['A', 'B', 'C'], $this->computeResult());
    }

    public function testDetectSmallCycle(): void
    {
        $this->addNodes('A', 'B');

        $this->addDependency('A', 'B');
        $this->addDependency('B', 'A');

        $this->expectException(CycleDetectedException::class);
        $this->computeResult();
    }

    public function testDetectLargerCycleNotIncludingStartNode(): void
    {
        $this->addNodes('A', 'B', 'C', 'D');

        $this->addDependency('A', 'B');
        $this->addDependency('B', 'C');
        $this->addDependency('C', 'D');
        $this->addDependency('D', 'B'); // closes the cycle B -> C -> D -> B

        $this->expectException(CycleDetectedException::class);
        $this->computeResult();
    }

    private function addNodes(string ...$names): void
    {
        foreach ($names as $name) {
            $node = new Node($name);
            $this->nodes[$name] = $node;
            $this->_calc->addNode($node->id, $node);
        }
    }

    private function addDependency(string $from, string $to, bool $optional = false): void
    {
        $this->_calc->addDependency($this->nodes[$from]->id, $this->nodes[$to]->id, $optional);
    }

    /**
     * @return list<string>
     */
    private function computeResult(): array
    {
        return array_map(static function (Node $n): string {
            return $n->name;
        }, array_values($this->_calc->sort()));
    }
}

class Node
{
    /** @var string */
    public $name;

    /** @var int */
    public $id;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->id = spl_object_id($this);
    }
}
