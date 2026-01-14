<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240129163944ChangeRoleTypeValues extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change the role.type values to use "user" and "operational".';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE role SET type = 'user' WHERE type = 'orga:user'");
        $this->addSql("UPDATE role SET type = 'operational' WHERE type = 'orga:tech'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE role SET type = 'orga:user' WHERE type = 'user'");
        $this->addSql("UPDATE role SET type = 'orga:tech' WHERE type = 'operational'");
    }
}
