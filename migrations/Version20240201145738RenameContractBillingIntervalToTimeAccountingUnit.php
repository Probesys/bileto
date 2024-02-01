<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240201145738RenameContractBillingIntervalToTimeAccountingUnit extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the contract billingInterval column to timeAccountingUnit.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract RENAME COLUMN billing_interval TO time_accounting_unit');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract CHANGE billing_interval time_accounting_unit INT DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract RENAME COLUMN time_accounting_unit TO billing_interval');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract CHANGE time_accounting_unit billing_interval INT DEFAULT 0 NOT NULL');
        }
    }
}
