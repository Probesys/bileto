<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// phpcs:disable Generic.Files.LineLength
final class Version20260423132512AddArchivedAtAndDeletedAtToOrganization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the archived_at and deleted_at columns to the organization table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE organization ADD archived_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE organization ADD deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE organization ADD archived_at DATETIME DEFAULT NULL
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE organization ADD deleted_at DATETIME DEFAULT NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE organization DROP archived_at
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE organization DROP deleted_at
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE organization DROP archived_at
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE organization DROP deleted_at
            SQL);
        }
    }
}
