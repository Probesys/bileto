<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230215153215CreateEntityEvent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the entity_event table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('CREATE SEQUENCE entity_event_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE entity_event (
                    id INT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    type VARCHAR(10) NOT NULL,
                    entity_type VARCHAR(255) NOT NULL,
                    entity_id INT NOT NULL,
                    changes JSON NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE INDEX IDX_975A3F5EB03A8386 ON entity_event (created_by_id)');
            $this->addSql('COMMENT ON COLUMN entity_event.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE entity_event
                ADD CONSTRAINT FK_975A3F5EB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql(<<<SQL
                CREATE TABLE entity_event (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    type VARCHAR(10) NOT NULL,
                    entity_type VARCHAR(255) NOT NULL,
                    entity_id INT NOT NULL,
                    changes LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
                    INDEX IDX_975A3F5EB03A8386 (created_by_id),
                    PRIMARY KEY(id)
                )
                DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE entity_event
                ADD CONSTRAINT FK_975A3F5EB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('DROP SEQUENCE entity_event_id_seq CASCADE');
            $this->addSql('ALTER TABLE entity_event DROP CONSTRAINT FK_975A3F5EB03A8386');
            $this->addSql('DROP TABLE entity_event');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE entity_event DROP FOREIGN KEY FK_975A3F5EB03A8386');
            $this->addSql('DROP TABLE entity_event');
        }
    }
}
