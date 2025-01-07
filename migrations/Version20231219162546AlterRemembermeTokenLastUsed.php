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

final class Version20231219162546AlterRemembermeTokenLastUsed extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter the rememberme_token lastused column to add datetime type.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE rememberme_token ALTER lastused TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
            $this->addSql('COMMENT ON COLUMN rememberme_token.lastused IS \'(DC2Type:datetime_immutable)\'');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                ALTER TABLE rememberme_token
                CHANGE lastUsed lastUsed DATETIME NOT NULL
                COMMENT '(DC2Type:datetime_immutable)'
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE rememberme_token ALTER lastused TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
            $this->addSql('COMMENT ON COLUMN rememberme_token.lastused IS NULL');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE rememberme_token CHANGE lastUsed lastUsed DATETIME NOT NULL');
        }
    }
}
