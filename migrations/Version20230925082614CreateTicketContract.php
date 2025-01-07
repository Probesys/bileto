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

final class Version20230925082614CreateTicketContract extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the ticket_contract table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE ticket_contract (
                    ticket_id INT NOT NULL,
                    contract_id INT NOT NULL,
                    PRIMARY KEY(ticket_id, contract_id)
                )
                SQL);
            $this->addSql('CREATE INDEX IDX_6CE6D4D8700047D2 ON ticket_contract (ticket_id)');
            $this->addSql('CREATE INDEX IDX_6CE6D4D82576E0FD ON ticket_contract (contract_id)');
            $this->addSql(<<<SQL
                ALTER TABLE ticket_contract
                ADD CONSTRAINT FK_6CE6D4D8700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket_contract
                ADD CONSTRAINT FK_6CE6D4D82576E0FD
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE ticket_contract (
                    ticket_id INT NOT NULL,
                    contract_id INT NOT NULL,
                    INDEX IDX_6CE6D4D8700047D2 (ticket_id),
                    INDEX IDX_6CE6D4D82576E0FD (contract_id),
                    PRIMARY KEY(ticket_id, contract_id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket_contract
                ADD CONSTRAINT FK_6CE6D4D8700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                ON DELETE CASCADE
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket_contract
                ADD CONSTRAINT FK_6CE6D4D82576E0FD
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
                ON DELETE CASCADE
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket_contract DROP CONSTRAINT FK_6CE6D4D8700047D2');
            $this->addSql('ALTER TABLE ticket_contract DROP CONSTRAINT FK_6CE6D4D82576E0FD');
            $this->addSql('DROP TABLE ticket_contract');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket_contract DROP FOREIGN KEY FK_6CE6D4D8700047D2');
            $this->addSql('ALTER TABLE ticket_contract DROP FOREIGN KEY FK_6CE6D4D82576E0FD');
            $this->addSql('DROP TABLE ticket_contract');
        }
    }
}
