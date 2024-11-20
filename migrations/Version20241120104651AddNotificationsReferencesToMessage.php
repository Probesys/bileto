<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

// phpcs:disable Generic.Files.LineLength
final class Version20241120104651AddNotificationsReferencesToMessage extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the notifications_references column to the message table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message ADD notifications_references JSON DEFAULT \'[]\' NOT NULL');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message ADD notifications_references JSON DEFAULT \'[]\' NOT NULL');
        }
    }

    public function postUp(Schema $schema): void
    {
        $dbMessages = $this->connection->fetchAllAssociative('SELECT id, email_id FROM message WHERE email_id IS NOT NULL');

        foreach ($dbMessages as $dbMessage) {
            if (str_starts_with($dbMessage['email_id'], 'GLPI_')) {
                $emailReference = "glpi:{$dbMessage['email_id']}";
            } else {
                $emailReference = "email:{$dbMessage['email_id']}";
            }

            $this->connection->update(
                'message',
                ['notifications_references' => [$emailReference]],
                ['id' => $dbMessage['id']],
                ['notifications_references' => Types::JSON],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message DROP notifications_references');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message DROP notifications_references');
        }
    }
}
