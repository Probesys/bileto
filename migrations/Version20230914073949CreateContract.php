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

final class Version20230914073949CreateContract extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the contract table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE contract_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE contract (
                    id INT NOT NULL,
                    created_by_id INT NOT NULL,
                    updated_by_id INT NOT NULL,
                    organization_id INT NOT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    start_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    end_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    max_hours INT NOT NULL,
                    notes TEXT NOT NULL,
                    PRIMARY KEY(id)
                )
                SQL);
            $this->addSql('CREATE INDEX IDX_E98F2859B03A8386 ON contract (created_by_id)');
            $this->addSql('CREATE INDEX IDX_E98F2859896DBBDE ON contract (updated_by_id)');
            $this->addSql('CREATE INDEX IDX_E98F285932C8A3DE ON contract (organization_id)');
            $this->addSql('COMMENT ON COLUMN contract.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN contract.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN contract.start_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN contract.end_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F2859B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F2859896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F285932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
                SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE contract (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT NOT NULL,
                    updated_by_id INT NOT NULL,
                    organization_id INT NOT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    name VARCHAR(255) NOT NULL,
                    start_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    end_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    max_hours INT NOT NULL,
                    notes LONGTEXT NOT NULL,
                    INDEX IDX_E98F2859B03A8386 (created_by_id),
                    INDEX IDX_E98F2859896DBBDE (updated_by_id),
                    INDEX IDX_E98F285932C8A3DE (organization_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F2859B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F2859896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
                SQL);
            $this->addSql(<<<SQL
                ALTER TABLE contract
                ADD CONSTRAINT FK_E98F285932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE contract_id_seq CASCADE');
            $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_E98F2859B03A8386');
            $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_E98F2859896DBBDE');
            $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_E98F285932C8A3DE');
            $this->addSql('DROP TABLE contract');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B03A8386');
            $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859896DBBDE');
            $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F285932C8A3DE');
            $this->addSql('DROP TABLE contract');
        }
    }
}
