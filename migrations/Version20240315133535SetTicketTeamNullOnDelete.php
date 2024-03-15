<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240315133535SetTicketTeamNullOnDelete extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set the ticket.team column to null on delete.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3296CD8AE');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                ON DELETE SET NULL
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3296CD8AE');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                ON DELETE SET NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3296CD8AE');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3296CD8AE');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
            SQL);
        }
    }
}
