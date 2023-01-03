<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221031151013AddLocaleToUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the locale column to the users table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE users ADD locale VARCHAR(5) DEFAULT \'en_GB\' NOT NULL');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE users ADD locale VARCHAR(5) DEFAULT \'en_GB\' NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "users" DROP locale');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `users` DROP locale');
        }
    }
}
