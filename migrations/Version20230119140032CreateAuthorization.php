<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230119140032CreateAuthorization extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the authorizations table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('CREATE SEQUENCE "authorizations_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql(<<<SQL
                CREATE TABLE "authorizations" (
                    id INT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    role_id INT NOT NULL,
                    holder_id INT NOT NULL,
                    organization_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                    PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_2BC15D69539B0606 ON "authorizations" (uid)');
            $this->addSql('CREATE INDEX IDX_2BC15D69B03A8386 ON "authorizations" (created_by_id)');
            $this->addSql('CREATE INDEX IDX_2BC15D69D60322AC ON "authorizations" (role_id)');
            $this->addSql('CREATE INDEX IDX_2BC15D69DEEE62D0 ON "authorizations" (holder_id)');
            $this->addSql('CREATE INDEX IDX_2BC15D6932C8A3DE ON "authorizations" (organization_id)');
            $this->addSql('COMMENT ON COLUMN "authorizations".created_at IS \'(DC2Type:datetimetz_immutable)\'');
            $this->addSql(<<<SQL
                ALTER TABLE "authorizations"
                ADD CONSTRAINT FK_2BC15D69B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE "authorizations"
                ADD CONSTRAINT FK_2BC15D69D60322AC
                FOREIGN KEY (role_id)
                REFERENCES role (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE "authorizations"
                ADD CONSTRAINT FK_2BC15D69DEEE62D0
                FOREIGN KEY (holder_id)
                REFERENCES "users" (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE "authorizations"
                ADD CONSTRAINT FK_2BC15D6932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql(<<<SQL
                CREATE TABLE `authorizations` (
                    id INT AUTO_INCREMENT NOT NULL,
                    created_by_id INT DEFAULT NULL,
                    role_id INT NOT NULL,
                    holder_id INT NOT NULL,
                    organization_id INT DEFAULT NULL,
                    uid VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetimetz_immutable)',
                    UNIQUE INDEX UNIQ_2BC15D69539B0606 (uid),
                    INDEX IDX_2BC15D69B03A8386 (created_by_id),
                    INDEX IDX_2BC15D69D60322AC (role_id),
                    INDEX IDX_2BC15D69DEEE62D0 (holder_id),
                    INDEX IDX_2BC15D6932C8A3DE (organization_id),
                    PRIMARY KEY(id)
                )
                DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE `authorizations`
                ADD CONSTRAINT FK_2BC15D69B03A8386
                FOREIGN KEY (created_by_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE `authorizations`
                ADD CONSTRAINT FK_2BC15D69D60322AC
                FOREIGN KEY (role_id)
                REFERENCES role (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE `authorizations`
                ADD CONSTRAINT FK_2BC15D69DEEE62D0
                FOREIGN KEY (holder_id)
                REFERENCES `users` (id)
            SQL);
            $this->addSql(<<<SQL
                ALTER TABLE `authorizations`
                ADD CONSTRAINT FK_2BC15D6932C8A3DE
                FOREIGN KEY (organization_id)
                REFERENCES organization (id)
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('DROP SEQUENCE "authorizations_id_seq" CASCADE');
            $this->addSql('ALTER TABLE "authorizations" DROP CONSTRAINT FK_2BC15D69B03A8386');
            $this->addSql('ALTER TABLE "authorizations" DROP CONSTRAINT FK_2BC15D69D60322AC');
            $this->addSql('ALTER TABLE "authorizations" DROP CONSTRAINT FK_2BC15D69DEEE62D0');
            $this->addSql('ALTER TABLE "authorizations" DROP CONSTRAINT FK_2BC15D6932C8A3DE');
            $this->addSql('DROP TABLE "authorizations"');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `authorizations` DROP FOREIGN KEY FK_2BC15D69B03A8386');
            $this->addSql('ALTER TABLE `authorizations` DROP FOREIGN KEY FK_2BC15D69D60322AC');
            $this->addSql('ALTER TABLE `authorizations` DROP FOREIGN KEY FK_2BC15D69DEEE62D0');
            $this->addSql('ALTER TABLE `authorizations` DROP FOREIGN KEY FK_2BC15D6932C8A3DE');
            $this->addSql('DROP TABLE `authorizations`');
        }
    }
}
