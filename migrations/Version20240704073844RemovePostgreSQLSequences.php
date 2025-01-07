<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240704073844RemovePostgreSQLSequences extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove remaining PostgreSQL sequences.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE authorizations_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE contract_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE entity_event_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE mailbox_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE mailbox_email_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE message_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE message_document_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE organization_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE role_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE team_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE team_authorization_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE ticket_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE time_spent_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE users_id_seq CASCADE');
            $this->addSql('DROP SEQUENCE messenger_messages_id_seq CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE "authorizations_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "contract_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "entity_event_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "mailbox_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "mailbox_email_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "message_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "message_document_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "organization_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "role_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "team_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "team_authorization_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "ticket_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "time_spent_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "user_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('CREATE SEQUENCE "messenger_messages_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        }
    }
}
