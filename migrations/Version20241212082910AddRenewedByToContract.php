<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// phpcs:disable Generic.Files.LineLength
final class Version20241212082910AddRenewedByToContract extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the renewed_by_id to the contract table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract ADD renewed_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B26CE351 FOREIGN KEY (renewed_by_id) REFERENCES contract (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_E98F2859B26CE351 ON contract (renewed_by_id)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract ADD renewed_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B26CE351 FOREIGN KEY (renewed_by_id) REFERENCES contract (id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_E98F2859B26CE351 ON contract (renewed_by_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_E98F2859B26CE351');
            $this->addSql('DROP INDEX UNIQ_E98F2859B26CE351');
            $this->addSql('ALTER TABLE contract DROP renewed_by_id');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B26CE351');
            $this->addSql('DROP INDEX UNIQ_E98F2859B26CE351 ON contract');
            $this->addSql('ALTER TABLE contract DROP renewed_by_id');
        }
    }
}
