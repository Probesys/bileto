<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230510080054AddUpdatedFields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the updated_at and updated_by fields to tables';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE authorizations ADD updated_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE authorizations ADD updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN authorizations.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE authorizations
                ADD CONSTRAINT FK_2BC15D69896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_2BC15D69896DBBDE ON authorizations (updated_by_id)');
            $this->addSql('ALTER TABLE entity_event ADD updated_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE entity_event ADD updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN entity_event.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE entity_event
                ADD CONSTRAINT FK_975A3F5E896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_975A3F5E896DBBDE ON entity_event (updated_by_id)');
            $this->addSql('ALTER TABLE message ADD updated_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE message ADD updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN message.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307F896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_B6BD307F896DBBDE ON message (updated_by_id)');
            $this->addSql('ALTER TABLE organization ADD updated_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE organization ADD updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN organization.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE organization
                ADD CONSTRAINT FK_C1EE637C896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_C1EE637C896DBBDE ON organization (updated_by_id)');
            $this->addSql('ALTER TABLE role ADD updated_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE role ADD updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN role.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE role
                ADD CONSTRAINT FK_57698A6A896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_57698A6A896DBBDE ON role (updated_by_id)');
            $this->addSql('ALTER TABLE ticket ADD updated_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE ticket ADD updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN ticket.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_97A0ADA3896DBBDE ON ticket (updated_by_id)');
            $this->addSql('ALTER TABLE users ADD updated_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE users ADD updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD CONSTRAINT FK_1483A5E9896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_1483A5E9896DBBDE ON users (updated_by_id)');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql(<<<SQL
                ALTER TABLE authorizations
                ADD updated_by_id INT DEFAULT NULL,
                ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE authorizations
                ADD CONSTRAINT FK_2BC15D69896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_2BC15D69896DBBDE ON authorizations (updated_by_id)');
            $this->addSql(<<<SQL
                ALTER TABLE entity_event
                ADD updated_by_id INT DEFAULT NULL,
                ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE entity_event
                ADD CONSTRAINT FK_975A3F5E896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_975A3F5E896DBBDE ON entity_event (updated_by_id)');
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD updated_by_id INT DEFAULT NULL,
                ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE message
                ADD CONSTRAINT FK_B6BD307F896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_B6BD307F896DBBDE ON message (updated_by_id)');
            $this->addSql(<<<SQL
                ALTER TABLE organization
                ADD updated_by_id INT DEFAULT NULL,
                ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE organization
                ADD CONSTRAINT FK_C1EE637C896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_C1EE637C896DBBDE ON organization (updated_by_id)');
            $this->addSql(<<<SQL
                ALTER TABLE role
                ADD updated_by_id INT DEFAULT NULL,
                ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE role
                ADD CONSTRAINT FK_57698A6A896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_57698A6A896DBBDE ON role (updated_by_id)');
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD updated_by_id INT DEFAULT NULL,
                ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE ticket
                ADD CONSTRAINT FK_97A0ADA3896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_97A0ADA3896DBBDE ON ticket (updated_by_id)');
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD updated_by_id INT DEFAULT NULL,
                ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD CONSTRAINT FK_1483A5E9896DBBDE
                FOREIGN KEY (updated_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_1483A5E9896DBBDE ON users (updated_by_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE organization DROP CONSTRAINT FK_C1EE637C896DBBDE');
            $this->addSql('DROP INDEX IDX_C1EE637C896DBBDE');
            $this->addSql('ALTER TABLE organization DROP updated_by_id');
            $this->addSql('ALTER TABLE organization DROP updated_at');
            $this->addSql('ALTER TABLE entity_event DROP CONSTRAINT FK_975A3F5E896DBBDE');
            $this->addSql('DROP INDEX IDX_975A3F5E896DBBDE');
            $this->addSql('ALTER TABLE entity_event DROP updated_by_id');
            $this->addSql('ALTER TABLE entity_event DROP updated_at');
            $this->addSql('ALTER TABLE role DROP CONSTRAINT FK_57698A6A896DBBDE');
            $this->addSql('DROP INDEX IDX_57698A6A896DBBDE');
            $this->addSql('ALTER TABLE role DROP updated_by_id');
            $this->addSql('ALTER TABLE role DROP updated_at');
            $this->addSql('ALTER TABLE "users" DROP CONSTRAINT FK_1483A5E9896DBBDE');
            $this->addSql('DROP INDEX IDX_1483A5E9896DBBDE');
            $this->addSql('ALTER TABLE "users" DROP updated_by_id');
            $this->addSql('ALTER TABLE "users" DROP updated_at');
            $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F896DBBDE');
            $this->addSql('DROP INDEX IDX_B6BD307F896DBBDE');
            $this->addSql('ALTER TABLE message DROP updated_by_id');
            $this->addSql('ALTER TABLE message DROP updated_at');
            $this->addSql('ALTER TABLE ticket DROP CONSTRAINT FK_97A0ADA3896DBBDE');
            $this->addSql('DROP INDEX IDX_97A0ADA3896DBBDE');
            $this->addSql('ALTER TABLE ticket DROP updated_by_id');
            $this->addSql('ALTER TABLE ticket DROP updated_at');
            $this->addSql('ALTER TABLE "authorizations" DROP CONSTRAINT FK_2BC15D69896DBBDE');
            $this->addSql('DROP INDEX IDX_2BC15D69896DBBDE');
            $this->addSql('ALTER TABLE "authorizations" DROP updated_by_id');
            $this->addSql('ALTER TABLE "authorizations" DROP updated_at');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3896DBBDE');
            $this->addSql('DROP INDEX IDX_97A0ADA3896DBBDE ON ticket');
            $this->addSql('ALTER TABLE ticket DROP updated_by_id, DROP updated_at');
            $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6A896DBBDE');
            $this->addSql('DROP INDEX IDX_57698A6A896DBBDE ON role');
            $this->addSql('ALTER TABLE role DROP updated_by_id, DROP updated_at');
            $this->addSql('ALTER TABLE `authorizations` DROP FOREIGN KEY FK_2BC15D69896DBBDE');
            $this->addSql('DROP INDEX IDX_2BC15D69896DBBDE ON `authorizations`');
            $this->addSql('ALTER TABLE `authorizations` DROP updated_by_id, DROP updated_at');
            $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F896DBBDE');
            $this->addSql('DROP INDEX IDX_B6BD307F896DBBDE ON message');
            $this->addSql('ALTER TABLE message DROP updated_by_id, DROP updated_at');
            $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C896DBBDE');
            $this->addSql('DROP INDEX IDX_C1EE637C896DBBDE ON organization');
            $this->addSql('ALTER TABLE organization DROP updated_by_id, DROP updated_at');
            $this->addSql('ALTER TABLE entity_event DROP FOREIGN KEY FK_975A3F5E896DBBDE');
            $this->addSql('DROP INDEX IDX_975A3F5E896DBBDE ON entity_event');
            $this->addSql('ALTER TABLE entity_event DROP updated_by_id, DROP updated_at');
            $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E9896DBBDE');
            $this->addSql('DROP INDEX IDX_1483A5E9896DBBDE ON `users`');
            $this->addSql('ALTER TABLE `users` DROP updated_by_id, DROP updated_at');
        }
    }
}
