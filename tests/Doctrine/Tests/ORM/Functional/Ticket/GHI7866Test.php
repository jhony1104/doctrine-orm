<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Tests\OrmFunctionalTestCase;

class GHI7866Test extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchemaForModels(GHI7866_User::class, GHI7866_UploadedFile::class);
    }

    public function testExtraUpdateInsert(): void
    {
        // Create and populate entities
        $uploadedFile = new GHI7866_UploadedFile();
        $user         = new GHI7866_User();

        $uploadedFile->owner        = $user;
        $uploadedFile->lastViewedBy = $user;
        $user->setLastUploadedFile  = $uploadedFile;

        try {
            $this->_em->persist($uploadedFile);
            $this->_em->persist($user);

            $this->_em->flush();

            self::assertTrue(true);
        } catch (NotNullConstraintViolationException $e) {
            self::fail('Insert was not possible');
        }
    }
}

/**
 * @ORM\Entity
 */
class GHI7866_User
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @ORM\OneToOne(targetEntity="GHI7866_UploadedFile")
     * @ORM\JoinColumn(nullable=true)
     *
     * @var ?GHI7866_UploadedFile
     */
    public $lastUploadedFile;
}

/**
 * @ORM\Entity
 */
class GHI7866_UploadedFile
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @ORM\OneToOne(targetEntity="GHI7866_User")
     * @ORM\JoinColumn(nullable=false)
     *
     * @var GHI7866_User
     */
    public $owner;

    /**
     * @ORM\OneToOne(targetEntity="GHI7866_User")
     * @ORM\JoinColumn(nullable=true)
     *
     * @var ?GHI7866_User
     */
    public $lastViewedBy;
}
