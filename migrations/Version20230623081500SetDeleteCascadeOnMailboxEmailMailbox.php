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

final class Version20230623081500SetDeleteCascadeOnMailboxEmailMailbox extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set DELETE CASCADE on mailbox_email.mailbox';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE mailbox_email DROP CONSTRAINT FK_6DAF24B466EC35CC');
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B466EC35CC
                FOREIGN KEY (mailbox_id)
                REFERENCES mailbox (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE mailbox_email DROP FOREIGN KEY FK_6DAF24B466EC35CC');
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B466EC35CC
                FOREIGN KEY (mailbox_id)
                REFERENCES mailbox (id) ON DELETE CASCADE
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE mailbox_email DROP CONSTRAINT fk_6daf24b466ec35cc');
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT fk_6daf24b466ec35cc
                FOREIGN KEY (mailbox_id)
                REFERENCES mailbox (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE mailbox_email DROP FOREIGN KEY FK_6DAF24B466EC35CC');
            $this->addSql(<<<SQL
                ALTER TABLE mailbox_email
                ADD CONSTRAINT FK_6DAF24B466EC35CC
                FOREIGN KEY (mailbox_id)
                REFERENCES mailbox (id)
            SQL);
        }
    }
}
