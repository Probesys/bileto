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
final class Version20241120143635DropMessageEmailId extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the email_id column from the message table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message DROP email_id');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message DROP email_id');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message ADD email_id VARCHAR(1000) DEFAULT NULL');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message ADD email_id VARCHAR(1000) DEFAULT NULL');
        }
    }

    public function postDown(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $sql = "SELECT id, notifications_references FROM message WHERE notifications_references::text != '[]'";
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $sql = "SELECT id, notifications_references FROM message WHERE notifications_references != '[]'";
        }
        $dbMessages = $this->connection->fetchAllAssociative($sql);

        foreach ($dbMessages as $dbMessage) {
            $references = json_decode($dbMessage['notifications_references'], true);

            if (!is_array($references) || empty($references)) {
                continue;
            }

            $reference = $references[0];

            if (str_starts_with($reference, 'glpi:')) {
                $emailId = substr($reference, strlen('glpi:'));
            } else {
                $emailId = substr($reference, strlen('email:'));
            }

            $this->connection->update(
                'message',
                ['email_id' => $emailId],
                ['id' => $dbMessage['id']],
                ['email_id' => Types::STRING],
            );
        }
    }
}
