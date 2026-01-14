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

final class Version20230607095044CreateMailbox extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the mailbox table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE mailbox_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE mailbox (
                    id INT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    host VARCHAR(255) NOT NULL,
                    protocol VARCHAR(10) NOT NULL,
                    port INT NOT NULL,
                    encryption VARCHAR(10) NOT NULL,
                    username VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    authentication VARCHAR(10) NOT NULL,
                    folder VARCHAR(255) NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_A69FE20B539B0606 ON mailbox (uid)');
            $this->addSql('CREATE INDEX IDX_A69FE20BB03A8386 ON mailbox (created_by_id)');
            $this->addSql('CREATE INDEX IDX_A69FE20B896DBBDE ON mailbox (updated_by_id)');
            $this->addSql('COMMENT ON COLUMN mailbox.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN mailbox.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE mailbox
                ADD CONSTRAINT FK_A69FE20BB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox
                ADD CONSTRAINT FK_A69FE20B896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE mailbox (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    name VARCHAR(255) NOT NULL,
                    host VARCHAR(255) NOT NULL,
                    protocol VARCHAR(10) NOT NULL,
                    port INT NOT NULL,
                    encryption VARCHAR(10) NOT NULL,
                    username VARCHAR(255) NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    authentication VARCHAR(10) NOT NULL,
                    folder VARCHAR(255) NOT NULL,
                    UNIQUE INDEX UNIQ_A69FE20B539B0606 (uid),
                    INDEX IDX_A69FE20BB03A8386 (created_by_id),
                    INDEX IDX_A69FE20B896DBBDE (updated_by_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox
                ADD CONSTRAINT FK_A69FE20BB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox
                ADD CONSTRAINT FK_A69FE20B896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE mailbox_id_seq CASCADE');
            $this->addSql('ALTER TABLE mailbox DROP CONSTRAINT FK_A69FE20BB03A8386');
            $this->addSql('ALTER TABLE mailbox DROP CONSTRAINT FK_A69FE20B896DBBDE');
            $this->addSql('DROP TABLE mailbox');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE mailbox DROP FOREIGN KEY FK_A69FE20BB03A8386');
            $this->addSql('ALTER TABLE mailbox DROP FOREIGN KEY FK_A69FE20B896DBBDE');
            $this->addSql('DROP TABLE mailbox');
        }
    }
}
