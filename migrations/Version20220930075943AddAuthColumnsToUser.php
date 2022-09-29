<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220930075943AddAuthColumnsToUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns for authentication to the user table';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "user" ADD email VARCHAR(255) NOT NULL');
            $this->addSql('ALTER TABLE "user" ADD roles JSON NOT NULL');
            $this->addSql('ALTER TABLE "user" ADD password VARCHAR(255) NOT NULL');
            $this->addSql('CREATE UNIQUE INDEX idx_user_email ON "user" (email)');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql(<<<SQL
                ALTER TABLE user
                ADD email VARCHAR(255) NOT NULL,
                ADD roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\',
                ADD password VARCHAR(255) NOT NULL
            SQL);
            $this->addSql('CREATE UNIQUE INDEX idx_user_email ON user (email)');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('CREATE SCHEMA public');
            $this->addSql('DROP INDEX idx_user_email');
            $this->addSql('ALTER TABLE "user" DROP email');
            $this->addSql('ALTER TABLE "user" DROP roles');
            $this->addSql('ALTER TABLE "user" DROP password');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('DROP INDEX idx_user_email ON `user`');
            $this->addSql('ALTER TABLE `user` DROP email, DROP roles, DROP password');
        }
    }
}
