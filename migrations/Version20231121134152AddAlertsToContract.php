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

final class Version20231121134152AddAlertsToContract extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add alerts columns to the contract table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract ADD hours_alert INT DEFAULT 0 NOT NULL');
            $this->addSql('ALTER TABLE contract ADD date_alert INT DEFAULT 0 NOT NULL');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract ADD hours_alert INT DEFAULT 0 NOT NULL');
            $this->addSql('ALTER TABLE contract ADD date_alert INT DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract DROP hours_alert');
            $this->addSql('ALTER TABLE contract DROP date_alert');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract DROP hours_alert');
            $this->addSql('ALTER TABLE contract DROP date_alert');
        }
    }
}
