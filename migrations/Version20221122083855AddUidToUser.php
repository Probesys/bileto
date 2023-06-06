<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221122083855AddUidToUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the uid column to the users table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE users ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9539B0606 ON users (uid)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE users ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9539B0606 ON users (uid)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP INDEX UNIQ_1483A5E9539B0606');
            $this->addSql('ALTER TABLE users DROP uid');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('DROP INDEX UNIQ_1483A5E9539B0606 ON users');
            $this->addSql('ALTER TABLE users DROP uid');
        }
    }
}
