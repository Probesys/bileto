<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

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
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "users" ADD roles JSON NOT NULL');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql("ALTER TABLE users ADD roles LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");
        }
    }
}
