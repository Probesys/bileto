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
final class Version20240826082958SetDefaultUserLdapIdentifierToEmpty extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set default user.ldapIdentifier column to empty string';
    }

    public function up(Schema $schema): void
    {

        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql("UPDATE users SET ldap_identifier = '' WHERE ldap_identifier IS NULL");
            $this->addSql('ALTER TABLE users ALTER ldap_identifier SET DEFAULT \'\'');
            $this->addSql('ALTER TABLE users ALTER ldap_identifier SET NOT NULL');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('UPDATE users SET ldap_identifier = "" WHERE ldap_identifier IS NULL');
            $this->addSql('ALTER TABLE users CHANGE ldap_identifier ldap_identifier VARCHAR(255) DEFAULT \'\' NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE "users" ALTER ldap_identifier DROP NOT NULL');
            $this->addSql('ALTER TABLE "users" ALTER ldap_identifier DROP DEFAULT');
            $this->addSql("UPDATE users SET ldap_identifier = NULL WHERE ldap_identifier = ''");
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE `users` CHANGE ldap_identifier ldap_identifier VARCHAR(255) DEFAULT NULL');
            $this->addSql('UPDATE users SET ldap_identifier = NULL WHERE ldap_identifier = ""');
        }
    }
}
