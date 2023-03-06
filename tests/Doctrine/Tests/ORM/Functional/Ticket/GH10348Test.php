<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

final class GH10348Test extends OrmFunctionalTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->setUpEntitySchema([
            GH10348ChildEntity::class,
            GH10348ParentEntity::class,
        ]);
    }

    public function testCanRemoveParentWithChildRelatesToOwnEntity(): void
    {
        $child1 = new GH10348ChildEntity();
        $child2 = new GH10348ChildEntity();
        $child2->origin = $child1;

        $parent = new GH10348ParentEntity();
        $parent->addChild($child1)->addChild($child2);

        $this->_em->persist($parent);
        $this->_em->flush();
    
        $parent = $this->_em->find(GH10348ParentEntity::class, $parent->id);

        $this->_em->remove($parent);

        $this->expectNotToPerformAssertions();
        $this->_em->flush();
    }
}

/**
 * @ORM\Entity
 * @ORM\Table(name="gh10348_child_entities")
 */
class GH10348ChildEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     *
     * @var ?int
     */
    public $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="GH10348ParentEntity", inversedBy="children")
     *
     * @var ?GH10348ParentEntity
     */
    public $parent = null;

    /**
     * @ORM\ManyToOne(targetEntity="GH10348ChildEntity", cascade={"remove"})
     *
     * @var ?GH10348ChildEntity
     */
    public $origin = null;
}

/**
 * @ORM\Entity
 * @ORM\Table(name="gh10348_parent_entities")
 */
class GH10348ParentEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     *
     * @var ?int
     */
    public $id = null;

    /**
     * @ORM\OneToMany(targetEntity="GH10348ChildEntity", mappedBy="parent", cascade={"persist", "remove"})
     *
     * @var Collection
     */
    private $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function addChild(GH10348ChildEntity $childEntity): self
    {
        $childEntity->parent = $this;
        $this->children->add($childEntity);

        return $this;
    }
}

