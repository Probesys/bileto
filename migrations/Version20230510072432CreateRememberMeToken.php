<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230510072432CreateRememberMeToken extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the rememberme_token table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql(<<<SQL
                CREATE TABLE rememberme_token (
                    series VARCHAR(88) NOT NULL,
                    value VARCHAR(88) NOT NULL,
                    lastUsed TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    class VARCHAR(100) NOT NULL,
                    username VARCHAR(200) NOT NULL,
                    PRIMARY KEY(series)
                )
            SQL);
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql(<<<SQL
                CREATE TABLE rememberme_token (
                    series VARCHAR(88) NOT NULL,
                    value VARCHAR(88) NOT NULL,
                    lastUsed DATETIME NOT NULL,
                    class VARCHAR(100) NOT NULL,
                    username VARCHAR(200) NOT NULL,
                    PRIMARY KEY(series)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rememberme_token');
    }
}
