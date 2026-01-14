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

final class Version20221102091809CreateTicket extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the ticket table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE ticket_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE ticket (
                    id INT NOT NULL,
                    created_by_id INT NOT NULL,
                    requester_id INT DEFAULT NULL,
                    assignee_id INT DEFAULT NULL,
                    organization_id INT NOT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    type VARCHAR(32) DEFAULT 'request' NOT NULL,
                    status VARCHAR(32) DEFAULT 'new' NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    urgency VARCHAR(32) DEFAULT 'medium' NOT NULL,
                    impact VARCHAR(32) DEFAULT 'medium' NOT NULL,
                    priority VARCHAR(32) DEFAULT 'medium' NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_97A0ADA3539B0606 ON ticket (uid)');
            $this->addSql('CREATE INDEX IDX_97A0ADA3B03A8386 ON ticket (created_by_id)');
            $this->addSql('CREATE INDEX IDX_97A0ADA3ED442CF4 ON ticket (requester_id)');
            $this->addSql('CREATE INDEX IDX_97A0ADA359EC7D60 ON ticket (assignee_id)');
            $this->addSql('CREATE INDEX IDX_97A0ADA332C8A3DE ON ticket (organization_id)');
            $this->addSql('COMMENT ON COLUMN ticket.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3ED442CF4
                FOREIGN KEY (requester_id)
                REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA359EC7D60
                FOREIGN KEY (assignee_id)
                REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA332C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE ticket (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT NOT NULL,
                    requester_id INT DEFAULT NULL,
                    assignee_id INT DEFAULT NULL,
                    organization_id INT NOT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    type VARCHAR(32) DEFAULT 'request' NOT NULL,
                    status VARCHAR(32) DEFAULT 'new' NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    urgency VARCHAR(32) DEFAULT 'medium' NOT NULL,
                    impact VARCHAR(32) DEFAULT 'medium' NOT NULL,
                    priority VARCHAR(32) DEFAULT 'medium' NOT NULL,
                    UNIQUE INDEX UNIQ_97A0ADA3539B0606 (uid),
                    INDEX IDX_97A0ADA3B03A8386 (created_by_id),
                    INDEX IDX_97A0ADA3ED442CF4 (requester_id),
                    INDEX IDX_97A0ADA359EC7D60 (assignee_id),
                    INDEX IDX_97A0ADA332C8A3DE (organization_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3ED442CF4
                FOREIGN KEY (requester_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA359EC7D60
                FOREIGN KEY (assignee_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA332C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE ticket_id_seq CASCADE');
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3B03A8386');
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3ED442CF4');
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA359EC7D60');
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA332C8A3DE');
            $this->addSql('DROP TABLE ticket');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B03A8386');
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3ED442CF4');
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA359EC7D60');
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA332C8A3DE');
            $this->addSql('DROP TABLE ticket');
        }
    }
}
