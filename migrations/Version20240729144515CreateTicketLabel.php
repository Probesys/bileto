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

final class Version20240729144515CreateTicketLabel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the ticket_label table.';
    }

    public function up(Schema $schema): void
    {
        // phpcs:disable Generic.Files.LineLength
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE ticket_label (ticket_id INT NOT NULL, label_id INT NOT NULL, PRIMARY KEY(ticket_id, label_id))');
            $this->addSql('CREATE INDEX IDX_26973363700047D2 ON ticket_label (ticket_id)');
            $this->addSql('CREATE INDEX IDX_2697336333B92F39 ON ticket_label (label_id)');
            $this->addSql('ALTER TABLE ticket_label ADD CONSTRAINT FK_26973363700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE ticket_label ADD CONSTRAINT FK_2697336333B92F39 FOREIGN KEY (label_id) REFERENCES label (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('CREATE TABLE ticket_label (ticket_id INT NOT NULL, label_id INT NOT NULL, INDEX IDX_26973363700047D2 (ticket_id), INDEX IDX_2697336333B92F39 (label_id), PRIMARY KEY(ticket_id, label_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
            $this->addSql('ALTER TABLE ticket_label ADD CONSTRAINT FK_26973363700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE ticket_label ADD CONSTRAINT FK_2697336333B92F39 FOREIGN KEY (label_id) REFERENCES label (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        // phpcs:disable Generic.Files.LineLength
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket_label DROP CONSTRAINT FK_26973363700047D2');
            $this->addSql('ALTER TABLE ticket_label DROP CONSTRAINT FK_2697336333B92F39');
            $this->addSql('DROP TABLE ticket_label');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket_label DROP FOREIGN KEY FK_26973363700047D2');
            $this->addSql('ALTER TABLE ticket_label DROP FOREIGN KEY FK_2697336333B92F39');
            $this->addSql('DROP TABLE ticket_label');
        }
    }
}
