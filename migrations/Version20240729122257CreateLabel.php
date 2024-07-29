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

final class Version20240729122257CreateLabel extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the label table.';
    }

    public function up(Schema $schema): void
    {
        // phpcs:disable Generic.Files.LineLength
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE label (id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, uid VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(250) NOT NULL, color VARCHAR(7) NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_EA750E8539B0606 ON label (uid)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_EA750E85E237E06 ON label (name)');
            $this->addSql('CREATE INDEX IDX_EA750E8B03A8386 ON label (created_by_id)');
            $this->addSql('CREATE INDEX IDX_EA750E8896DBBDE ON label (updated_by_id)');
            $this->addSql('ALTER TABLE label ADD CONSTRAINT FK_EA750E8B03A8386 FOREIGN KEY (created_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->addSql('ALTER TABLE label ADD CONSTRAINT FK_EA750E8896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('CREATE TABLE label (id INT AUTO_INCREMENT NOT NULL, uid VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(250) NOT NULL, color VARCHAR(7) NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_EA750E8539B0606 (uid), UNIQUE INDEX UNIQ_EA750E85E237E06 (name), INDEX IDX_EA750E8B03A8386 (created_by_id), INDEX IDX_EA750E8896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
            $this->addSql('ALTER TABLE label ADD CONSTRAINT FK_EA750E8B03A8386 FOREIGN KEY (created_by_id) REFERENCES `users` (id)');
            $this->addSql('ALTER TABLE label ADD CONSTRAINT FK_EA750E8896DBBDE FOREIGN KEY (updated_by_id) REFERENCES `users` (id)');
        }
        // phpcs:enable
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($dbPlatform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE label DROP CONSTRAINT FK_EA750E8B03A8386');
            $this->addSql('ALTER TABLE label DROP CONSTRAINT FK_EA750E8896DBBDE');
            $this->addSql('DROP TABLE label');
        } elseif ($dbPlatform instanceof MariaDBPlatform) {
            $this->addSql('ALTER TABLE label DROP FOREIGN KEY FK_EA750E8B03A8386');
            $this->addSql('ALTER TABLE label DROP FOREIGN KEY FK_EA750E8896DBBDE');
            $this->addSql('DROP TABLE label');
        }
    }
}