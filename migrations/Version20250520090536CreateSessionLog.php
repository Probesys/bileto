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
final class Version20250520090536CreateSessionLog extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the session_log table.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql(<<<'SQL'
                CREATE TABLE session_log (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, uid VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, type VARCHAR(32) NOT NULL, identifier VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL, session_id_hash VARCHAR(64) NOT NULL, http_headers JSON NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, PRIMARY KEY(id))
            SQL);
            $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX UNIQ_F2E6F0FF539B0606 ON session_log (uid)
            SQL);
            $this->addSql(<<<'SQL'
                CREATE INDEX IDX_F2E6F0FFB03A8386 ON session_log (created_by_id)
            SQL);
            $this->addSql(<<<'SQL'
                CREATE INDEX IDX_F2E6F0FF896DBBDE ON session_log (updated_by_id)
            SQL);
            $this->addSql(<<<'SQL'
                CREATE INDEX IDX_F2E6F0FF772E836A ON session_log (identifier)
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log ADD CONSTRAINT FK_F2E6F0FFB03A8386 FOREIGN KEY (created_by_id) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log ADD CONSTRAINT FK_F2E6F0FF896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<'SQL'
                CREATE TABLE session_log (id INT AUTO_INCREMENT NOT NULL, uid VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, type VARCHAR(32) NOT NULL, identifier VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL, session_id_hash VARCHAR(64) NOT NULL, http_headers JSON NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F2E6F0FF539B0606 (uid), INDEX IDX_F2E6F0FFB03A8386 (created_by_id), INDEX IDX_F2E6F0FF896DBBDE (updated_by_id), INDEX IDX_F2E6F0FF772E836A (identifier), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log ADD CONSTRAINT FK_F2E6F0FFB03A8386 FOREIGN KEY (created_by_id) REFERENCES `users` (id) ON DELETE SET NULL
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log ADD CONSTRAINT FK_F2E6F0FF896DBBDE FOREIGN KEY (updated_by_id) REFERENCES `users` (id) ON DELETE SET NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log DROP CONSTRAINT FK_F2E6F0FFB03A8386
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log DROP CONSTRAINT FK_F2E6F0FF896DBBDE
            SQL);
            $this->addSql(<<<'SQL'
                DROP TABLE session_log
            SQL);
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log DROP FOREIGN KEY FK_F2E6F0FFB03A8386
            SQL);
            $this->addSql(<<<'SQL'
                ALTER TABLE session_log DROP FOREIGN KEY FK_F2E6F0FF896DBBDE
            SQL);
            $this->addSql(<<<'SQL'
                DROP TABLE session_log
            SQL);
        }
    }
}
