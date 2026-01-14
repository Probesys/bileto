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

final class Version20220930075943AddAuthColumnsToUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns for authentication to the user table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE "user" ADD email VARCHAR(255) NOT NULL');
            $this->addSql('ALTER TABLE "user" ADD roles JSON NOT NULL');
            $this->addSql('ALTER TABLE "user" ADD password VARCHAR(255) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9e7927c74 ON "user" (email)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<SQL
                ALTER TABLE user
                ADD email VARCHAR(255) NOT NULL,
                ADD roles LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
                ADD password VARCHAR(255) NOT NULL
            SQL);
            $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9e7927c74 ON user (email)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP INDEX uniq_1483a5e9e7927c74');
            $this->addSql('ALTER TABLE "user" DROP email');
            $this->addSql('ALTER TABLE "user" DROP roles');
            $this->addSql('ALTER TABLE "user" DROP password');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('DROP INDEX uniq_1483a5e9e7927c74 ON `user`');
            $this->addSql('ALTER TABLE `user` DROP email, DROP roles, DROP password');
        }
    }
}
