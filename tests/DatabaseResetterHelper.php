<?php

namespace App\Tests;

trait DatabaseResetterHelper
{
    /**
     * @before
     */
    public function resetDatabase(): void
    {
        $kernel = self::createKernel();
        $kernel->boot();

        /** @var \Doctrine\Persistence\ManagerRegistry */
        $doctrine = $kernel->getContainer()->get('doctrine');

        /** @var \Doctrine\ORM\EntityManager */
        $entityManager = $doctrine->getManager();

        $connection = $entityManager->getConnection();

        $tablesNames = $connection->getSchemaManager()->listTableNames();
        $tablesNames = implode(',', $tablesNames);

        $dbPlatform = $connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $connection->executeStatement(<<<SQL
                SET session_replication_role = 'replica';
                TRUNCATE TABLE {$tablesNames};
                SET session_replication_role = 'origin';
            SQL);
        } elseif ($dbPlatform === 'mysql') {
            $connection->executeStatement(<<<SQL
                SET foreign_key_checks = 0;
                TRUNCATE TABLE {$tablesNames};
                SET foreign_key_checks = 1;
            SQL);
        }

        $kernel->shutdown();
    }
}
