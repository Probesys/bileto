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

// phpcs:disable Generic.Files.LineLength
final class Version20241008151834AddResponsibleTeamToOrganization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the responsibleTeam column to the organization table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE organization ADD responsible_team_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C33AF12A3 FOREIGN KEY (responsible_team_id) REFERENCES team (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('CREATE INDEX IDX_C1EE637C33AF12A3 ON organization (responsible_team_id)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE organization ADD responsible_team_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C33AF12A3 FOREIGN KEY (responsible_team_id) REFERENCES team (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_C1EE637C33AF12A3 ON organization (responsible_team_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE organization DROP CONSTRAINT FK_C1EE637C33AF12A3');
            $this->addSql('DROP INDEX IDX_C1EE637C33AF12A3');
            $this->addSql('ALTER TABLE organization DROP responsible_team_id');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C33AF12A3');
            $this->addSql('DROP INDEX IDX_C1EE637C33AF12A3 ON organization');
            $this->addSql('ALTER TABLE organization DROP responsible_team_id');
        }
    }
}
