<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230817141440CreateMessageDocument extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the message_document table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE message_document_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE message_document (
                    id INT NOT NULL,
                    created_by_id INT NOT NULL,
                    updated_by_id INT DEFAULT NULL,
                    message_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    mimetype VARCHAR(100) NOT NULL,
                    hash VARCHAR(255) NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_D14F4E67539B0606 ON message_document (uid)');
            $this->addSql('CREATE INDEX IDX_D14F4E67B03A8386 ON message_document (created_by_id)');
            $this->addSql('CREATE INDEX IDX_D14F4E67896DBBDE ON message_document (updated_by_id)');
            $this->addSql('CREATE INDEX IDX_D14F4E67537A1329 ON message_document (message_id)');
            $this->addSql('COMMENT ON COLUMN message_document.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN message_document.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE message_document
                ADD CONSTRAINT FK_D14F4E67B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message_document
                ADD CONSTRAINT FK_D14F4E67896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message_document
                ADD CONSTRAINT FK_D14F4E67537A1329
                FOREIGN KEY (message_id)
                REFERENCES message (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE message_document (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT NOT NULL,
                    updated_by_id INT DEFAULT NULL,
                    message_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    name VARCHAR(255) NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    mimetype VARCHAR(100) NOT NULL,
                    hash VARCHAR(255) NOT NULL,
                    UNIQUE INDEX UNIQ_D14F4E67539B0606 (uid),
                    INDEX IDX_D14F4E67B03A8386 (created_by_id),
                    INDEX IDX_D14F4E67896DBBDE (updated_by_id),
                    INDEX IDX_D14F4E67537A1329 (message_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message_document
                ADD CONSTRAINT FK_D14F4E67B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message_document
                ADD CONSTRAINT FK_D14F4E67896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message_document
                ADD CONSTRAINT FK_D14F4E67537A1329
                FOREIGN KEY (message_id)
                REFERENCES message (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE message_document_id_seq CASCADE');
            $this->addSql('ALTER TABLE message_document DROP CONSTRAINT FK_D14F4E67B03A8386');
            $this->addSql('ALTER TABLE message_document DROP CONSTRAINT FK_D14F4E67896DBBDE');
            $this->addSql('ALTER TABLE message_document DROP CONSTRAINT FK_D14F4E67537A1329');
            $this->addSql('DROP TABLE message_document');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message_document DROP FOREIGN KEY FK_D14F4E67B03A8386');
            $this->addSql('ALTER TABLE message_document DROP FOREIGN KEY FK_D14F4E67896DBBDE');
            $this->addSql('ALTER TABLE message_document DROP FOREIGN KEY FK_D14F4E67537A1329');
            $this->addSql('DROP TABLE message_document');
        }
    }
}
