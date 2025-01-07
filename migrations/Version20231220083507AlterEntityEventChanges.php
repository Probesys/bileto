<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231220083507AlterEntityEventChanges extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter the column changes of the table entity_event.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE entity_event CHANGE changes changes JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE entity_event CHANGE changes changes JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        }
    }
}
