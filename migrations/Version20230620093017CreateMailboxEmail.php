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

final class Version20230620093017CreateMailboxEmail extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the mailbox_email table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE mailbox_email_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE mailbox_email (
                    id INT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    mailbox_id INT NOT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    raw TEXT NOT NULL,
                    last_error TEXT NOT NULL,
                    last_error_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_6DAF24B4539B0606 ON mailbox_email (uid)');
            $this->addSql('CREATE INDEX IDX_6DAF24B4B03A8386 ON mailbox_email (created_by_id)');
            $this->addSql('CREATE INDEX IDX_6DAF24B4896DBBDE ON mailbox_email (updated_by_id)');
            $this->addSql('CREATE INDEX IDX_6DAF24B466EC35CC ON mailbox_email (mailbox_id)');
            $this->addSql('COMMENT ON COLUMN mailbox_email.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN mailbox_email.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN mailbox_email.last_error_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B4B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B4896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B466EC35CC
                FOREIGN KEY (mailbox_id)
                REFERENCES mailbox (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE mailbox_email (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    mailbox_id INT NOT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    raw LONGTEXT NOT NULL,
                    last_error LONGTEXT NOT NULL,
                    last_error_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    UNIQUE INDEX UNIQ_6DAF24B4539B0606 (uid),
                    INDEX IDX_6DAF24B4B03A8386 (created_by_id),
                    INDEX IDX_6DAF24B4896DBBDE (updated_by_id),
                    INDEX IDX_6DAF24B466EC35CC (mailbox_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B4B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B4896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B466EC35CC
                FOREIGN KEY (mailbox_id)
                REFERENCES mailbox (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE mailbox_email_id_seq CASCADE');
            $this->addSql('ALTER TABLE mailbox_email DROP CONSTRAINT FK_6DAF24B4B03A8386');
            $this->addSql('ALTER TABLE mailbox_email DROP CONSTRAINT FK_6DAF24B4896DBBDE');
            $this->addSql('ALTER TABLE mailbox_email DROP CONSTRAINT FK_6DAF24B466EC35CC');
            $this->addSql('DROP TABLE mailbox_email');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE mailbox_email DROP FOREIGN KEY FK_6DAF24B4B03A8386');
            $this->addSql('ALTER TABLE mailbox_email DROP FOREIGN KEY FK_6DAF24B4896DBBDE');
            $this->addSql('ALTER TABLE mailbox_email DROP FOREIGN KEY FK_6DAF24B466EC35CC');
            $this->addSql('DROP TABLE mailbox_email');
        }
    }
}
