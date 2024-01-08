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

final class Version20221123133721MoveSolutionToTicket extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop message.is_solution and replace by ticket.solution';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message DROP is_solution');
            $this->addSql('ALTER TABLE ticket ADD solution_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA31C0BE183
                FOREIGN KEY (solution_id)
                REFERENCES message (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_97A0ADA31C0BE183 ON ticket (solution_id)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message DROP is_solution');
            $this->addSql('ALTER TABLE ticket ADD solution_id INT DEFAULT NULL');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA31C0BE183
                FOREIGN KEY (solution_id)
                REFERENCES message (id)
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_97A0ADA31C0BE183 ON ticket (solution_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message ADD is_solution BOOLEAN NOT NULL');
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA31C0BE183');
            $this->addSql('DROP INDEX UNIQ_97A0ADA31C0BE183');
            $this->addSql('ALTER TABLE ticket DROP solution_id');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA31C0BE183');
            $this->addSql('DROP INDEX UNIQ_97A0ADA31C0BE183 ON ticket');
            $this->addSql('ALTER TABLE ticket DROP solution_id');
            $this->addSql('ALTER TABLE message ADD is_solution TINYINT(1) NOT NULL');
        }
    }
}
