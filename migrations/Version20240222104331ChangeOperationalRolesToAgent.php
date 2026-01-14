<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240222104331ChangeOperationalRolesToAgent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change the roles "operational" to "agent".';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE role SET type = 'agent' WHERE type = 'operational'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE role SET type = 'operational' WHERE type = 'agent'");
    }
}
