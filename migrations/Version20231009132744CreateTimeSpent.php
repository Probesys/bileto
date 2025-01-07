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

final class Version20231009132744CreateTimeSpent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the time_spent table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE time_spent_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE time_spent (
                    id INT NOT NULL,
                    created_by_id INT NOT NULL,
                    updated_by_id INT NOT NULL,
                    ticket_id INT NOT NULL,
                    contract_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    time INT NOT NULL,
                    real_time INT NOT NULL,
                    PRIMARY KEY(id)
                )
                SQL);
            $this->addSql('CREATE INDEX IDX_B417D625B03A8386 ON time_spent (created_by_id)');
            $this->addSql('CREATE INDEX IDX_B417D625896DBBDE ON time_spent (updated_by_id)');
            $this->addSql('CREATE INDEX IDX_B417D625700047D2 ON time_spent (ticket_id)');
            $this->addSql('CREATE INDEX IDX_B417D6252576E0FD ON time_spent (contract_id)');
            $this->addSql('COMMENT ON COLUMN time_spent.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN time_spent.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D6252576E0FD
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE time_spent (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT NOT NULL,
                    updated_by_id INT NOT NULL,
                    ticket_id INT NOT NULL,
                    contract_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    time INT NOT NULL,
                    real_time INT NOT NULL,
                    INDEX IDX_B417D625B03A8386 (created_by_id),
                    INDEX IDX_B417D625896DBBDE (updated_by_id),
                    INDEX IDX_B417D625700047D2 (ticket_id),
                    INDEX IDX_B417D6252576E0FD (contract_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D625700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE time_spent
                ADD CONSTRAINT FK_B417D6252576E0FD
                FOREIGN KEY (contract_id)
                REFERENCES contract (id)
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE time_spent_id_seq CASCADE');
            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT FK_B417D625B03A8386');
            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT FK_B417D625896DBBDE');
            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT FK_B417D625700047D2');
            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT FK_B417D6252576E0FD');
            $this->addSql('DROP TABLE time_spent');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D625B03A8386');
            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D625896DBBDE');
            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D625700047D2');
            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D6252576E0FD');
            $this->addSql('DROP TABLE time_spent');
        }
    }
}
