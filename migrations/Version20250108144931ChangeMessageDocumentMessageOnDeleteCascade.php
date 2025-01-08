<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// phpcs:disable Generic.Files.LineLength
final class Version20250108144931ChangeMessageDocumentMessageOnDeleteCascade extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change the message_document.message on delete to CASCADE';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message_document DROP CONSTRAINT FK_D14F4E67537A1329');
            $this->addSql('ALTER TABLE message_document ADD CONSTRAINT FK_D14F4E67537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message_document DROP FOREIGN KEY FK_D14F4E67537A1329');
            $this->addSql('ALTER TABLE message_document ADD CONSTRAINT FK_D14F4E67537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message_document DROP CONSTRAINT fk_d14f4e67537a1329');
            $this->addSql('ALTER TABLE message_document ADD CONSTRAINT fk_d14f4e67537a1329 FOREIGN KEY (message_id) REFERENCES message (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message_document DROP FOREIGN KEY FK_D14F4E67537A1329');
            $this->addSql('ALTER TABLE message_document ADD CONSTRAINT FK_D14F4E67537A1329 FOREIGN KEY (message_id) REFERENCES message (id)');
        }
    }
}
