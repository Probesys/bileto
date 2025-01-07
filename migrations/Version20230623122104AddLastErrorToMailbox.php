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

final class Version20230623122104AddLastErrorToMailbox extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_error* columns to the mailbox table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE mailbox ADD last_error TEXT NOT NULL');
            $this->addSql('ALTER TABLE mailbox ADD last_error_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
            $this->addSql('COMMENT ON COLUMN mailbox.last_error_at IS \'(DC2Type:datetimetz_immutable)\'');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                ALTER TABLE mailbox
                ADD last_error LONGTEXT NOT NULL,
                ADD last_error_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE mailbox DROP last_error');
            $this->addSql('ALTER TABLE mailbox DROP last_error_at');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE mailbox DROP last_error, DROP last_error_at');
        }
    }
}
