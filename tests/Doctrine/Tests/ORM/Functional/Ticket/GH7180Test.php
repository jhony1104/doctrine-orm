<?php

declare(strict_types=1);

namespace Doctrine\Tests\Functional\Ticket;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * Tests suggested in https://github.com/doctrine/orm/pull/7180#issuecomment-380841413 and
 * https://github.com/doctrine/orm/pull/7180#issuecomment-381067448.
 *
 * @group 7180
 */
final class GH7180Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpEntitySchema([DDC7180A::class, DDC7180B::class, DDC7180C::class, DDC7180D::class, DDC7180E::class, DDC7180F::class, DDC7180G::class]);
    }

    public function testIssue(): void
    {
        $a = new DDC7180A();
        $b = new DDC7180B();
        $c = new DDC7180C();

        $a->b = $b;
        $b->a = $a;
        $c->a = $a;

        $this->_em->persist($a);
        $this->_em->persist($b);
        $this->_em->persist($c);

        $this->_em->flush();

        self::assertIsInt($a->id);
        self::assertIsInt($b->id);
        self::assertIsInt($c->id);
    }

    public function testIssue3NodeCycle(): void
    {
        $d = new DDC7180D();
        $e = new DDC7180E();
        $f = new DDC7180F();
        $g = new DDC7180G();

        $d->e = $e;
        $e->f = $f;
        $f->d = $d;
        $g->d = $d;

        $this->_em->persist($d);
        $this->_em->persist($e);
        $this->_em->persist($f);
        $this->_em->persist($g);

        $this->_em->flush();

        self::assertIsInt($d->id);
        self::assertIsInt($e->id);
        self::assertIsInt($f->id);
        self::assertIsInt($g->id);
    }
}

/**
 * @Entity
 */
class DDC7180A
{
    /**
     * @GeneratedValue()
     * @Id @Column(type="integer")
     */
    public $id;
    /**
     * @OneToOne(targetEntity=DDC7180B::class, inversedBy="a")
     * @JoinColumn(nullable=false)
     */
    public $b;
}

/**
 * @Entity
 */
class DDC7180B
{
    /**
     * @GeneratedValue()
     * @Id
     * @Column(type="integer")
     */
    public $id;
    /**
     * @OneToOne(targetEntity=DDC7180A::class, mappedBy="b")
     * @JoinColumn(nullable=true)
     */
    public $a;
}

/**
 * @Entity
 */
class DDC7180C
{
    /**
     * @GeneratedValue()
     * @Id @Column(type="integer")
     */
    public $id;
    /**
     * @ManyToOne(targetEntity=DDC7180A::class)
     * @JoinColumn(nullable=false)
     */
    public $a;
}

/**
 * @Entity
 */
class DDC7180D
{
    /**
     * @GeneratedValue()
     * @Id @Column(type="integer")
     */
    public $id;
    /**
     * @OneToOne(targetEntity=DDC7180E::class)
     * @JoinColumn(nullable=false)
     */
    public $e;
}

/**
 * @Entity
 */
class DDC7180E
{
    /**
     * @GeneratedValue()
     * @Id @Column(type="integer")
     */
    public $id;
    /**
     * @OneToOne(targetEntity=DDC7180F::class)
     * @JoinColumn(nullable=false)
     */
    public $f;
}

/**
 * @Entity
 */
class DDC7180F
{
    /**
     * @GeneratedValue()
     * @Id @Column(type="integer")
     */
    public $id;
    /**
     * @ManyToOne(targetEntity=DDC7180D::class)
     * @JoinColumn(nullable=true)
     */
    public $d;
}

/**
 * @Entity
 */
class DDC7180G
{
    /**
     * @GeneratedValue()
     * @Id @Column(type="integer")
     */
    public $id;
    /**
     * @ManyToOne(targetEntity=DDC7180D::class)
     * @JoinColumn(nullable=false)
     */
    public $d;
}
