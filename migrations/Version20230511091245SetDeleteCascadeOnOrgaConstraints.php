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

final class Version20230511091245SetDeleteCascadeOnOrgaConstraints extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set DELETE CASCADE on organization foreign keys in authorizations and ticket tables.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE authorizations DROP CONSTRAINT FK_2BC15D6932C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE authorizations
                ADD CONSTRAINT FK_2BC15D6932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA332C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA332C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F700047D2');
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307F700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE authorizations DROP FOREIGN KEY FK_2BC15D6932C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE authorizations
                ADD CONSTRAINT FK_2BC15D6932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE
            SQL);

            $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F700047D2');
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307F700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                ON DELETE CASCADE
            SQL);

            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA332C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA332C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                ON DELETE CASCADE
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT fk_97a0ada332c8a3de');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT fk_97a0ada332c8a3de
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE "authorizations" DROP CONSTRAINT fk_2bc15d6932c8a3de');
            $this->addSql(<<<SQL
                ALTER TABLE "authorizations"
                ADD CONSTRAINT fk_2bc15d6932c8a3de
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);

            $this->addSql('ALTER TABLE message DROP CONSTRAINT fk_b6bd307f700047d2');
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT fk_b6bd307f700047d2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE `authorizations` DROP FOREIGN KEY FK_2BC15D6932C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE `authorizations`
                ADD CONSTRAINT FK_2BC15D6932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
            SQL);

            $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F700047D2');
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307F700047D2
                FOREIGN KEY (ticket_id)
                REFERENCES ticket (id)
            SQL);

            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA332C8A3DE');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA332C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
            SQL);
        }
    }
}
