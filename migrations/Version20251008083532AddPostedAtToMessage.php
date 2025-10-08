<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251008083532AddPostedAtToMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add posted_at column to the message table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message ADD posted_at TIMESTAMP(0) WITH TIME ZONE');
            $this->addSql('UPDATE message SET posted_at = created_at');
            $this->addSql('ALTER TABLE message ALTER posted_at SET NOT NULL');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message ADD posted_at DATETIME');
            $this->addSql('UPDATE message SET posted_at = created_at');
            $this->addSql('ALTER TABLE message CHANGE posted_at posted_at DATETIME NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message DROP posted_at');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message DROP posted_at');
        }
    }
}
