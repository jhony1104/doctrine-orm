<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM;

use Doctrine\ORM\Internal\CommitOrderCalculator;
use Doctrine\Tests\OrmTestCase;
use stdClass;

/**
 * Tests of the commit order calculation.
 *
 * IMPORTANT: When writing tests here consider that a lot of graph constellations
 * can have many valid orderings, so you may want to build a graph that has only
 * 1 valid order to simplify your tests.
 */
class CommitOrderCalculatorTest extends OrmTestCase
{
    /** @var CommitOrderCalculator */
    private $_calc;

    protected function setUp(): void
    {
        $this->_calc = new CommitOrderCalculator();
    }

    public function testCommitOrdering1(): void
    {
        $class1 = new stdClass();
        $class2 = new stdClass();
        $class3 = new stdClass();
        $class4 = new stdClass();
        $class5 = new stdClass();

        $this->_calc->addNode(spl_object_id($class1), $class1);
        $this->_calc->addNode(spl_object_id($class2), $class2);
        $this->_calc->addNode(spl_object_id($class3), $class3);
        $this->_calc->addNode(spl_object_id($class4), $class4);
        $this->_calc->addNode(spl_object_id($class5), $class5);

        $this->_calc->addDependency(spl_object_id($class1), spl_object_id($class2), true);
        $this->_calc->addDependency(spl_object_id($class2), spl_object_id($class3), true);
        $this->_calc->addDependency(spl_object_id($class3), spl_object_id($class4), true);
        $this->_calc->addDependency(spl_object_id($class5), spl_object_id($class1), true);

        $sorted = $this->_calc->sort();

        // There is only 1 valid ordering for this constellation
        $correctOrder = [$class5, $class1, $class2, $class3, $class4];

        self::assertSame($correctOrder, array_values($sorted));
    }

    public function testCommitOrdering2(): void
    {
        $class1 = new stdCLass;
        $class2 = new stdCLass;

        $this->_calc->addNode(spl_object_id($class1), $class1);
        $this->_calc->addNode(spl_object_id($class2), $class2);

        $this->_calc->addDependency(spl_object_id($class1), spl_object_id($class2), false);
        $this->_calc->addDependency(spl_object_id($class2), spl_object_id($class1), true);

        $sorted = $this->_calc->sort();

        // There is only 1 valid ordering for this constellation
        $correctOrder = [$class2, $class1];

        self::assertSame($correctOrder, array_values($sorted));
    }

    public function testCommitOrdering3(): void
    {
        // this test corresponds to the GH7259Test::testPersistFileBeforeVersion functional test
        $class1 = new stdClass;
        $class2 = new stdClass;
        $class3 = new stdClass;
        $class4 = new stdClass;

        $this->_calc->addNode(spl_object_id($class1), $class1);
        $this->_calc->addNode(spl_object_id($class2), $class2);
        $this->_calc->addNode(spl_object_id($class3), $class3);
        $this->_calc->addNode(spl_object_id($class4), $class4);

        $this->_calc->addDependency(spl_object_id($class4), spl_object_id($class1), true);
        $this->_calc->addDependency(spl_object_id($class1), spl_object_id($class2), true);
        $this->_calc->addDependency(spl_object_id($class4), spl_object_id($class3), true);
        $this->_calc->addDependency(spl_object_id($class1), spl_object_id($class4), false);

        $sorted = $this->_calc->sort();

        // There is only multiple valid ordering for this constellation, but
        // the class4, class1, class2 ordering is important to break the cycle
        // on the nullable link.
        $correctOrders = [
            [$class4, $class1, $class2, $class3],
            [$class4, $class1, $class3, $class2],
            [$class4, $class3, $class1, $class2],
        ];

        // We want to perform a strict comparison of the array
        self::assertContains(array_values($sorted), $correctOrders, '', false, true);
    }

    public function testNodesMaintainOrderWhenNoDepencency(): void
    {
        $class1 = new stdCLass;
        $class2 = new stdCLass;

        $this->_calc->addNode(spl_object_id($class1), $class1);
        $this->_calc->addNode(spl_object_id($class2), $class2);

        $sorted = $this->_calc->sort();

        // Nodes that are not constrained by dependencies shall maintain the order
        // in which they were added
        $correctOrder = [$class1, $class2];

        self::assertSame($correctOrder, array_values($sorted));
    }

}
