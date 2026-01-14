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

// phpcs:disable Generic.Files.LineLength
final class Version20250522075934AddLoginDisabledAt extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the login_disabled_at to the users table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE users ADD login_disabled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE users ADD login_disabled_at DATETIME DEFAULT NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE "users" DROP login_disabled_at
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE `users` DROP login_disabled_at
            SQL);
        }
    }
}
