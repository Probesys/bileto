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

final class Version20240222153018CreateTeamAuthorization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the team_authorization table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE team_authorization_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE team_authorization (
                    id INT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    team_id INT NOT NULL,
                    role_id INT NOT NULL,
                    organization_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_F0FAE7E539B0606 ON team_authorization (uid)');
            $this->addSql('CREATE INDEX IDX_F0FAE7EB03A8386 ON team_authorization (created_by_id)');
            $this->addSql('CREATE INDEX IDX_F0FAE7E896DBBDE ON team_authorization (updated_by_id)');
            $this->addSql('CREATE INDEX IDX_F0FAE7E296CD8AE ON team_authorization (team_id)');
            $this->addSql('CREATE INDEX IDX_F0FAE7ED60322AC ON team_authorization (role_id)');
            $this->addSql('CREATE INDEX IDX_F0FAE7E32C8A3DE ON team_authorization (organization_id)');
            $this->addSql('COMMENT ON COLUMN team_authorization.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN team_authorization.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7EB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7E896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7E296CD8AE296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7ED60322ACD60322AC
                FOREIGN KEY (role_id)
                REFERENCES role (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7E32C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('ALTER TABLE authorizations ADD team_authorization_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE authorizations
                ADD CONSTRAINT FK_2BC15D6998150C93
                FOREIGN KEY (team_authorization_id)
                REFERENCES team_authorization (id)
                ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_2BC15D6998150C93 ON authorizations (team_authorization_id)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE team_authorization (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    updated_by_id INT DEFAULT NULL,
                    team_id INT NOT NULL,
                    role_id INT NOT NULL,
                    organization_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    UNIQUE INDEX UNIQ_F0FAE7E539B0606 (uid),
                    INDEX IDX_F0FAE7EB03A8386 (created_by_id),
                    INDEX IDX_F0FAE7E896DBBDE (updated_by_id),
                    INDEX IDX_F0FAE7E296CD8AE (team_id),
                    INDEX IDX_F0FAE7ED60322AC (role_id),
                    INDEX IDX_F0FAE7E32C8A3DE (organization_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7EB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7E896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7E296CD8AE296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                ON DELETE CASCADE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7ED60322ACD60322AC
                FOREIGN KEY (role_id)
                REFERENCES role (id)
                ON DELETE CASCADE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE team_authorization
                ADD CONSTRAINT FK_F0FAE7E32C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE
            SQL);
            $this->addSql('ALTER TABLE authorizations ADD team_authorization_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE authorizations
                ADD CONSTRAINT FK_2BC15D6998150C93
                FOREIGN KEY (team_authorization_id)
                REFERENCES team_authorization (id)
                ON DELETE CASCADE
            SQL);
            $this->addSql('CREATE INDEX IDX_2BC15D6998150C93 ON authorizations (team_authorization_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE "authorizations" DROP CONSTRAINT FK_2BC15D6998150C93');
            $this->addSql('DROP SEQUENCE team_authorization_id_seq CASCADE');
            $this->addSql('ALTER TABLE team_authorization DROP CONSTRAINT FK_F0FAE7EB03A8386');
            $this->addSql('ALTER TABLE team_authorization DROP CONSTRAINT FK_F0FAE7E896DBBDE');
            $this->addSql('ALTER TABLE team_authorization DROP CONSTRAINT FK_F0FAE7E296CD8AE296CD8AE');
            $this->addSql('ALTER TABLE team_authorization DROP CONSTRAINT FK_F0FAE7ED60322ACD60322AC');
            $this->addSql('ALTER TABLE team_authorization DROP CONSTRAINT FK_F0FAE7E32C8A3DE');
            $this->addSql('DROP TABLE team_authorization');
            $this->addSql('DROP INDEX IDX_2BC15D6998150C93');
            $this->addSql('ALTER TABLE "authorizations" DROP team_authorization_id');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE `authorizations` DROP FOREIGN KEY FK_2BC15D6998150C93');
            $this->addSql('ALTER TABLE team_authorization DROP FOREIGN KEY FK_F0FAE7EB03A8386');
            $this->addSql('ALTER TABLE team_authorization DROP FOREIGN KEY FK_F0FAE7E896DBBDE');
            $this->addSql('ALTER TABLE team_authorization DROP FOREIGN KEY FK_F0FAE7E296CD8AE296CD8AE');
            $this->addSql('ALTER TABLE team_authorization DROP FOREIGN KEY FK_F0FAE7ED60322ACD60322AC');
            $this->addSql('ALTER TABLE team_authorization DROP FOREIGN KEY FK_F0FAE7E32C8A3DE');
            $this->addSql('DROP TABLE team_authorization');
            $this->addSql('DROP INDEX IDX_2BC15D6998150C93 ON `authorizations`');
            $this->addSql('ALTER TABLE `authorizations` DROP team_authorization_id');
        }
    }
}
