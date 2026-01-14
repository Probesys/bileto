<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230214161800AddMetaFieldsToMessageOrganizationAndUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meta fields to the message, organization and user tables';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE message ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_B6BD307F539B0606 ON message (uid)');
            $this->addSql('ALTER TABLE organization ADD created_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE organization ADD created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN organization.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE organization
                ADD CONSTRAINT FK_C1EE637CB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_C1EE637CB03A8386 ON organization (created_by_id)');
            $this->addSql('ALTER TABLE users ADD created_by_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE users ADD created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL');
            $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD CONSTRAINT FK_1483A5E9B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql('CREATE INDEX IDX_1483A5E9B03A8386 ON users (created_by_id)');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE message ADD uid VARCHAR(20) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_B6BD307F539B0606 ON message (uid)');
            $this->addSql(<<<SQL
                ALTER TABLE organization
                ADD created_by_id INT DEFAULT NULL,
                ADD created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE organization
                ADD CONSTRAINT FK_C1EE637CB03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_C1EE637CB03A8386 ON organization (created_by_id)');
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD created_by_id INT DEFAULT NULL,
                ADD created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)'
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE users
                ADD CONSTRAINT FK_1483A5E9B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql('CREATE INDEX IDX_1483A5E9B03A8386 ON users (created_by_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE organization DROP CONSTRAINT FK_C1EE637CB03A8386');
            $this->addSql('DROP INDEX IDX_C1EE637CB03A8386');
            $this->addSql('ALTER TABLE organization DROP created_by_id');
            $this->addSql('ALTER TABLE organization DROP created_at');
            $this->addSql('ALTER TABLE "users" DROP CONSTRAINT FK_1483A5E9B03A8386');
            $this->addSql('DROP INDEX IDX_1483A5E9B03A8386');
            $this->addSql('ALTER TABLE "users" DROP created_by_id');
            $this->addSql('ALTER TABLE "users" DROP created_at');
            $this->addSql('DROP INDEX UNIQ_B6BD307F539B0606');
            $this->addSql('ALTER TABLE message DROP uid');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('DROP INDEX UNIQ_B6BD307F539B0606 ON message');
            $this->addSql('ALTER TABLE message DROP uid');
            $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CB03A8386');
            $this->addSql('DROP INDEX IDX_C1EE637CB03A8386 ON organization');
            $this->addSql('ALTER TABLE organization DROP created_by_id, DROP created_at');
            $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E9B03A8386');
            $this->addSql('DROP INDEX IDX_1483A5E9B03A8386 ON `users`');
            $this->addSql('ALTER TABLE `users` DROP created_by_id, DROP created_at');
        }
    }
}
