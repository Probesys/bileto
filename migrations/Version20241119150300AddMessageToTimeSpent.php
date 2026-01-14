<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// phpcs:disable Generic.Files.LineLength
final class Version20241119150300AddMessageToTimeSpent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the message_id column to the time_spent table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE time_spent ADD message_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE time_spent ADD CONSTRAINT FK_B417D625537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('CREATE INDEX IDX_B417D625537A1329 ON time_spent (message_id)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE time_spent ADD message_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE time_spent ADD CONSTRAINT FK_B417D625537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_B417D625537A1329 ON time_spent (message_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE time_spent DROP CONSTRAINT FK_B417D625537A1329');
            $this->addSql('DROP INDEX IDX_B417D625537A1329');
            $this->addSql('ALTER TABLE time_spent DROP message_id');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE time_spent DROP FOREIGN KEY FK_B417D625537A1329');
            $this->addSql('DROP INDEX IDX_B417D625537A1329 ON time_spent');
            $this->addSql('ALTER TABLE time_spent DROP message_id');
        }
    }
}
