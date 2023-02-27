<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group GH6499
 */
class GH6499OneToOneRelationshipTest extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->_schemaTool->createSchema(
            [
                $this->_em->getClassMetadata(GH6499OTOA::class),
                $this->_em->getClassMetadata(GH6499OTOB::class),
            ]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->_schemaTool->dropSchema(
            [
                $this->_em->getClassMetadata(GH6499OTOA::class),
                $this->_em->getClassMetadata(GH6499OTOB::class),
            ]
        );
    }

    /**
     * Test for the bug described in issue #6499.
     */
    public function testIssue(): void
    {
        $a = new GH6499OTOA();

        $this->_em->persist($a);
        $this->_em->flush();
        $this->_em->clear();

        self::assertEquals($this->_em->find(GH6499OTOA::class, $a->id)->b->id, $a->b->id, 'Issue #6499 will result in a Integrity constraint violation before reaching this point.');
    }
}

/** @ORM\Entity */
class GH6499OTOA
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     *
     * @var int
     */
    public $id;

    /**
     * @ORM\OneToOne(targetEntity="GH6499OTOB", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     *
     * @var GH6499OTOB
     */
    public $b;

    public function __construct()
    {
        $this->b = new GH6499OTOB();
    }
}

/** @ORM\Entity */
class GH6499OTOB
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     *
     * @var int
     */
    public $id;
}
