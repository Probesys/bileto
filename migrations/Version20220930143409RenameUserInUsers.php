<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220930143409RenameUserInUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the table user in users.';
    }

    public function up(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "user" RENAME TO "users"');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `user` RENAME TO `users`');
        }
    }

    public function down(Schema $schema): void
    {
        $dbPlatform = $this->connection->getDatabasePlatform()->getName();
        if ($dbPlatform === 'postgresql') {
            $this->addSql('ALTER TABLE "users" RENAME TO "user"');
        } elseif ($dbPlatform === 'mysql') {
            $this->addSql('ALTER TABLE `users` RENAME TO `user`');
        }
    }
}