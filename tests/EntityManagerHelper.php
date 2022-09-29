<?php

namespace App\Tests;

use Doctrine\ORM\EntityRepository;

trait EntityManagerHelper
{
    /** @var \Doctrine\ORM\EntityManager */
    protected static $entityManager;

    /**
     * @beforeClass
     */
    public static function setUpEntityManagerHelper(): void
    {
        $kernel = self::createKernel();
        $kernel->boot();

        /** @var \Doctrine\Persistence\ManagerRegistry */
        $doctrine = $kernel->getContainer()->get('doctrine');

        /** @var \Doctrine\ORM\EntityManager */
        $entityManager = $doctrine->getManager();

        self::$entityManager = $entityManager;

        $kernel->shutdown();
    }

    /**
     * @afterClass
     */
    public static function tearDownEntityManagerHelper(): void
    {
        self::$entityManager->close();
        self::$entityManager = null; /** @phpstan-ignore-line */
    }

    /**
     * @template T of object
     * @param class-string<T> $entityName
     * @return EntityRepository<T>
     */
    public static function getRepository(string $entityName): EntityRepository
    {
        return self::$entityManager->getRepository($entityName);
    }
}
