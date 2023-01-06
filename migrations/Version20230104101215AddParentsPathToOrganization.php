<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230104101215AddParentsPathToOrganization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the parents_path column to the organization table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization ADD parents_path VARCHAR(255) NOT NULL DEFAULT '/'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization DROP parents_path');
    }
}
