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

final class Version20230118103344CreateRole extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the role table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE role_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE role (
                    id INT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    name VARCHAR(50) NOT NULL,
                    description VARCHAR(255) NOT NULL,
                    type VARCHAR(32) NOT NULL,
                    permissions TEXT NOT NULL,
                    is_default BOOLEAN NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_57698A6A539B0606 ON role (uid)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_57698A6A5E237E06 ON role (name)');
            $this->addSql('CREATE INDEX IDX_57698A6AB03A8386 ON role (created_by_id)');
            $this->addSql('COMMENT ON COLUMN role.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql('COMMENT ON COLUMN role.permissions IS \'(DC2Type:array)\'');
            $this->addSql(<<<SQL
                ALTER TABLE role
                ADD CONSTRAINT FK_57698A6AB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                CREATE TABLE role (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    name VARCHAR(50) NOT NULL,
                    description VARCHAR(255) NOT NULL,
                    type VARCHAR(32) NOT NULL,
                    permissions LONGTEXT NOT NULL COMMENT '(DC2Type:array)',
                    is_default BOOLEAN NOT NULL,
                    UNIQUE INDEX UNIQ_57698A6A539B0606 (uid),
                    UNIQUE INDEX UNIQ_57698A6A5E237E06 (name),
                    INDEX IDX_57698A6AB03A8386 (created_by_id),
                    PRIMARY KEY(id)
                )
                DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE role
                ADD CONSTRAINT FK_57698A6AB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE role_id_seq CASCADE');
            $this->addSql('ALTER TABLE role DROP CONSTRAINT FK_57698A6AB03A8386');
            $this->addSql('DROP TABLE role');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6AB03A8386');
            $this->addSql('DROP TABLE role');
        }
    }
}
