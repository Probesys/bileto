<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230606132457AddOrganizationToUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an organization_id column to the users table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE users ADD organization_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD CONSTRAINT FK_1483A5E932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE SET NULL
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_1483A5E932C8A3DE ON users (organization_id)');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE users ADD organization_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD CONSTRAINT FK_1483A5E932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE SET NULL
            SQL);
            $this->addSql('CREATE INDEX IDX_1483A5E932C8A3DE ON users (organization_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "users" DROP CONSTRAINT FK_1483A5E932C8A3DE');
            $this->addSql('DROP INDEX IDX_1483A5E932C8A3DE');
            $this->addSql('ALTER TABLE "users" DROP organization_id');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E932C8A3DE');
            $this->addSql('DROP INDEX IDX_1483A5E932C8A3DE ON `users`');
            $this->addSql('ALTER TABLE `users` DROP organization_id');
        }
    }
}
