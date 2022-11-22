<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

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
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE users ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9539B0606 ON users (uid)');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE users ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9539B0606 ON users (uid)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('DROP INDEX UNIQ_1483A5E9539B0606');
            $this->addSql('ALTER TABLE users DROP uid');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('DROP INDEX UNIQ_1483A5E9539B0606 ON users');
            $this->addSql('ALTER TABLE users DROP uid');
        }
    }
}
