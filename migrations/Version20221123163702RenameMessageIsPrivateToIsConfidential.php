<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20221123163702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the message column is_private to is_confidential';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE message RENAME COLUMN is_private TO is_confidential');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE message CHANGE is_private is_confidential TINYINT(1) NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE message RENAME COLUMN is_confidential TO is_private');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE message CHANGE is_confidential is_private TINYINT(1) NOT NULL');
        }
    }
}
