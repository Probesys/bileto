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

final class Version20240313133127AddTeamToTicket extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the team column to the ticket table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket ADD team_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_97A0ADA3296CD8AE ON ticket (team_id)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket ADD team_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3296CD8AE
                FOREIGN KEY (team_id)
                REFERENCES team (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_97A0ADA3296CD8AE ON ticket (team_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3296CD8AE');
            $this->addSql('DROP INDEX IDX_97A0ADA3296CD8AE');
            $this->addSql('ALTER TABLE ticket DROP team_id');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3296CD8AE');
            $this->addSql('DROP INDEX IDX_97A0ADA3296CD8AE ON ticket');
            $this->addSql('ALTER TABLE ticket DROP team_id');
        }
    }
}
