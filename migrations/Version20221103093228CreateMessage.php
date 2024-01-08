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

final class Version20221103093228CreateMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the message table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE message_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE message (
                    id INT NOT NULL,
                    created_by_id INT NOT NULL,
                    ticket_id INT NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    is_private BOOLEAN NOT NULL,
                    is_solution BOOLEAN NOT NULL,
                    via VARCHAR(32) DEFAULT 'webapp' NOT NULL,
                    content TEXT NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE INDEX IDX_B6BD307FB03A8386 ON message (created_by_id)');
            $this->addSql('CREATE INDEX IDX_B6BD307F700047D2 ON message (ticket_id)');
            $this->addSql('COMMENT ON COLUMN message.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307FB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307F700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE message (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT NOT NULL,
                    ticket_id INT NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    is_private TINYINT(1) NOT NULL,
                    is_solution TINYINT(1) NOT NULL,
                    via VARCHAR(32) DEFAULT 'webapp' NOT NULL,
                    content LONGTEXT NOT NULL,
                    INDEX IDX_B6BD307FB03A8386 (created_by_id),
                    INDEX IDX_B6BD307F700047D2 (ticket_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307FB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307F700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE message_id_seq CASCADE');
            $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307FB03A8386');
            $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F700047D2');
            $this->addSql('DROP TABLE message');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FB03A8386');
            $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F700047D2');
            $this->addSql('DROP TABLE message');
        }
    }
}
