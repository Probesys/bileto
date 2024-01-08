<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221031163001AddUidToOrganization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the uid column to the organization table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE organization ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C539B0606 ON organization (uid)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE organization ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C539B0606 ON organization (uid)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP INDEX UNIQ_C1EE637C539B0606');
            $this->addSql('ALTER TABLE organization DROP uid');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('DROP INDEX UNIQ_C1EE637C539B0606 ON organization');
            $this->addSql('ALTER TABLE organization DROP uid');
        }
    }
}
