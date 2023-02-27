<?php

declare(strict_types=1);

namespace Doctrine\Tests\Models\PersistentObject;

use Doctrine\Common\Persistence\PersistentObject;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

/** @Entity */
class PersistentEntity extends PersistentObject
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var int
     */
    protected $id;

    /**
     * @Column(type="string", length=255)
     * @var string
     */
    protected $name;

    /**
     * @ORM\JoinColumn(nullable=true)
     *
     * @ManyToOne(targetEntity="PersistentEntity")
     * @var PersistentEntity
     */
    protected $parent;
}
