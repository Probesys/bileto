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

final class Version20230217142448AddIndexOnEntityEvent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an index on EntityEvent (entityType, entityId).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_975A3F5EC412EE0281257D5D ON entity_event (entity_type, entity_id)');
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP INDEX IDX_975A3F5EC412EE0281257D5D');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('DROP INDEX IDX_975A3F5EC412EE0281257D5D ON entity_event');
        }
    }
}
