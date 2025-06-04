<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20250603134754AddAnonymizedToUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the anonymized columns to the users table.';
    }

    public function up(Schema $schema): void
    {
        $usersTable = $schema->getTable('users');
        $usersTable->addColumn('anonymized_at', Types::DATETIMETZ_IMMUTABLE, ['notnull' => false]);
        $usersTable->addColumn('anonymized_by_id', Types::INTEGER, ['notnull' => false]);
        $usersTable->addForeignKeyConstraint('users', ['anonymized_by_id'], ['id'], ['onDelete' => 'SET NULL']);
        $usersTable->addIndex(['anonymized_by_id']);
    }

    public function down(Schema $schema): void
    {
        $usersTable = $schema->getTable('users');
        $usersTable->dropColumn('anonymized_at');
        $usersTable->removeForeignKey('FK_1483A5E9E6B04A5D');
        $usersTable->dropIndex('IDX_1483A5E9E6B04A5D');
        $usersTable->dropColumn('anonymized_by_id');
    }
}
