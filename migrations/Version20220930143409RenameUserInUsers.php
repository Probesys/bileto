<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220930143409RenameUserInUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the table user in users.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "user" RENAME TO "users"');
            $this->addSql('ALTER SEQUENCE "user_id_seq" RENAME TO "users_id_seq"');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `user` RENAME TO `users`');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "users" RENAME TO "user"');
            $this->addSql('ALTER SEQUENCE "users_id_seq" RENAME TO "user_id_seq"');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `users` RENAME TO `user`');
        }
    }
}
