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

final class Version20240222134259CreateTeam extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the team table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE team_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE team (
                    id INT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    name VARCHAR(50) NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C4E0A61F539B0606 ON team (uid)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C4E0A61F5E237E06 ON team (name)');
            $this->addSql('CREATE INDEX IDX_C4E0A61FB03A8386 ON team (created_by_id)');
            $this->addSql('CREATE INDEX IDX_C4E0A61F896DBBDE ON team (updated_by_id)');
            $this->addSql('COMMENT ON COLUMN team.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN team.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                CREATE TABLE team_user (
                    team_id INT NOT NULL,
                    user_id INT NOT NULL,
                    PRIMARY KEY(team_id, user_id)
                )
            SQL);
            $this->addSql('CREATE INDEX IDX_5C722232296CD8AE ON team_user (team_id)');
            $this->addSql('CREATE INDEX IDX_5C722232A76ED395 ON team_user (user_id)');
            $this->addSql(<<<SQL
                ALTER TABLE team
                ADD CONSTRAINT FK_C4E0A61FB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team
                ADD CONSTRAINT FK_C4E0A61F896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_user
                ADD CONSTRAINT FK_5C722232296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_user
                ADD CONSTRAINT FK_5C722232A76ED395
                FOREIGN KEY (user_id)
                REFERENCES "users" (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE team (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    name VARCHAR(50) NOT NULL,
                    UNIQUE INDEX UNIQ_C4E0A61F539B0606 (uid),
                    UNIQUE INDEX UNIQ_C4E0A61F5E237E06 (name),
                    INDEX IDX_C4E0A61FB03A8386 (created_by_id),
                    INDEX IDX_C4E0A61F896DBBDE (updated_by_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                CREATE TABLE team_user (
                    team_id INT NOT NULL,
                    user_id INT NOT NULL,
                    INDEX IDX_5C722232296CD8AE (team_id),
                    INDEX IDX_5C722232A76ED395 (user_id),
                    PRIMARY KEY(team_id, user_id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team
                ADD CONSTRAINT FK_C4E0A61FB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team
                ADD CONSTRAINT FK_C4E0A61F896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_user
                ADD CONSTRAINT FK_5C722232296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                ON DELETE CASCADE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_user
                ADD CONSTRAINT FK_5C722232A76ED395
                FOREIGN KEY (user_id)
                REFERENCES `users` (id)
                ON DELETE CASCADE
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE team_id_seq CASCADE');
            $this->addSql('ALTER TABLE team DROP CONSTRAINT FK_C4E0A61FB03A8386');
            $this->addSql('ALTER TABLE team DROP CONSTRAINT FK_C4E0A61F896DBBDE');
            $this->addSql('ALTER TABLE team_user DROP CONSTRAINT FK_5C722232296CD8AE');
            $this->addSql('ALTER TABLE team_user DROP CONSTRAINT FK_5C722232A76ED395');
            $this->addSql('DROP TABLE team');
            $this->addSql('DROP TABLE team_user');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61FB03A8386');
            $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F896DBBDE');
            $this->addSql('ALTER TABLE team_user DROP FOREIGN KEY FK_5C722232296CD8AE');
            $this->addSql('ALTER TABLE team_user DROP FOREIGN KEY FK_5C722232A76ED395');
            $this->addSql('DROP TABLE team');
            $this->addSql('DROP TABLE team_user');
        }
    }
}
