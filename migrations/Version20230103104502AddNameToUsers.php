<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230103104502AddNameToUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the name column to the users table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE users ADD name VARCHAR(100) DEFAULT NULL');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE users ADD name VARCHAR(100) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "users" DROP name');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `users` DROP name');
        }
    }
}
