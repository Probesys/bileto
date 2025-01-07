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
final class Version20250107141916ChangeTicketSolutionOnDeleteSetNull extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change the ticket.solution on delete to SET NULL';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA31C0BE183');
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA31C0BE183 FOREIGN KEY (solution_id) REFERENCES message (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA31C0BE183');
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA31C0BE183 FOREIGN KEY (solution_id) REFERENCES message (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT fk_97a0ada31c0be183');
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT fk_97a0ada31c0be183 FOREIGN KEY (solution_id) REFERENCES message (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA31C0BE183');
            $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA31C0BE183 FOREIGN KEY (solution_id) REFERENCES message (id)');
        }
    }
}
