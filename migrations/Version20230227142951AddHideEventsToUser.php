<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230227142951AddHideEventsToUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the hide_events column to the users table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE users ADD hide_events BOOLEAN NOT NULL');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE users ADD hide_events TINYINT(1) NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "users" DROP hide_events');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `users` DROP hide_events');
        }
    }
}
