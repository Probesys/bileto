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

final class Version20240619125014SetDeleteCascadeOnContractAndTimeSpent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set DELETE CASCADE on contract.organization and time_spent.ticket columns.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_E98F285932C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F285932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT FK_B417D625700047D2');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT FK_B417D6252576E0FD');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D6252576E0FD
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
                ON DELETE SET NULL
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F285932C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F285932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D625700047D2');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                ON DELETE CASCADE
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D6252576E0FD');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D6252576E0FD
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
                ON DELETE SET NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE contract DROP CONSTRAINT fk_e98f285932c8a3de');
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT fk_e98f285932c8a3de
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT fk_b417d625700047d2');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT fk_b417d625700047d2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT fk_b417d6252576e0fd');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT fk_b417d6252576e0fd
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F285932C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F285932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D625700047D2');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
            SQL);

            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D6252576E0FD');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D6252576E0FD
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
            SQL);
        }
    }
}
