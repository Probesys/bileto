<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230106093003RemoveOrganizationNameUniqueness extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the unique index on the organization name column';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('DROP INDEX UNIQ_C1EE637C5E237E06');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('DROP INDEX UNIQ_C1EE637C5E237E06 on `organization`');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C5E237E06 ON organization (name)');
    }
}
