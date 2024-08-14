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

final class Version20240814075419AddObserversToOrganization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the observers relation to the organization table';
    }

    public function up(Schema $schema): void
    {
        // phpcs:disable Generic.Files.LineLength
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE organization_user (organization_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(organization_id, user_id))');
            $this->addSql('CREATE INDEX IDX_B49AE8D432C8A3DE ON organization_user (organization_id)');
            $this->addSql('CREATE INDEX IDX_B49AE8D4A76ED395 ON organization_user (user_id)');
            $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('CREATE TABLE organization_user (organization_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B49AE8D432C8A3DE (organization_id), INDEX IDX_B49AE8D4A76ED395 (user_id), PRIMARY KEY(organization_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
            $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE organization_user DROP CONSTRAINT FK_B49AE8D432C8A3DE');
            $this->addSql('ALTER TABLE organization_user DROP CONSTRAINT FK_B49AE8D4A76ED395');
            $this->addSql('DROP TABLE organization_user');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D432C8A3DE');
            $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D4A76ED395');
            $this->addSql('DROP TABLE organization_user');
        }
    }
}
