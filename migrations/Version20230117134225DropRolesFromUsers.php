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

final class Version20230117134225DropRolesFromUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the roles column from the users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP roles');
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE "users" ADD roles JSON NOT NULL');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql("ALTER TABLE users ADD roles LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");
        }
    }
}
