<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

// phpcs:disable Generic.Files.LineLength
final class Version20250604070746UpdateForeignKeysFromUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update foreign keys from user';
    }

    public function up(Schema $schema): void
    {
        $tableTicket = $schema->getTable('ticket');
        $tableTicket->removeForeignKey('FK_97A0ADA359EC7D60');
        $tableTicket->addForeignKeyConstraint('users', ['assignee_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_97A0ADA359EC7D60');

        $tableTicket->removeForeignKey('FK_97A0ADA3B03A8386');
        $tableTicket->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_97A0ADA3B03A8386');

        $tableTicket->removeForeignKey('FK_97A0ADA3896DBBDE');
        $tableTicket->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_97A0ADA3896DBBDE');

        $tableTicket->removeForeignKey('FK_97A0ADA3ED442CF4');
        $tableTicket->addForeignKeyConstraint('users', ['requester_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_97A0ADA3ED442CF4');

        $tableEntityEvent = $schema->getTable('entity_event');
        $tableEntityEvent->removeForeignKey('FK_975A3F5EB03A8386');
        $tableEntityEvent->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_975A3F5EB03A8386');

        $tableEntityEvent->removeForeignKey('FK_975A3F5E896DBBDE');
        $tableEntityEvent->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_975A3F5E896DBBDE');

        $tableAuthorization = $schema->getTable('authorizations');
        $tableAuthorization->removeForeignKey('FK_2BC15D69B03A8386');
        $tableAuthorization->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_2BC15D69B03A8386');

        $tableAuthorization->removeForeignKey('FK_2BC15D69896DBBDE');
        $tableAuthorization->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_2BC15D69896DBBDE');

        $tableAuthorization->removeForeignKey('FK_2BC15D69DEEE62D0');
        $tableAuthorization->addForeignKeyConstraint('users', ['holder_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_2BC15D69DEEE62D0');

        $tableContract = $schema->getTable('contract');
        $tableContract->removeForeignKey('FK_E98F2859B03A8386');
        $tableContract->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_E98F2859B03A8386');

        $tableContract->removeForeignKey('FK_E98F2859896DBBDE');
        $tableContract->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_E98F2859896DBBDE');

        $tableLabel = $schema->getTable('label');
        $tableLabel->removeForeignKey('FK_EA750E8B03A8386');
        $tableLabel->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_EA750E8B03A8386');

        $tableLabel->removeForeignKey('FK_EA750E8896DBBDE');
        $tableLabel->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_EA750E8896DBBDE');

        $tableMailbox = $schema->getTable('mailbox');
        $tableMailbox->removeForeignKey('FK_A69FE20BB03A8386');
        $tableMailbox->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_A69FE20BB03A8386');

        $tableMailbox->removeForeignKey('FK_A69FE20B896DBBDE');
        $tableMailbox->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_A69FE20B896DBBDE');

        $tableMailboxEmail = $schema->getTable('mailbox_email');
        $tableMailboxEmail->removeForeignKey('FK_6DAF24B4B03A8386');
        $tableMailboxEmail->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_6DAF24B4B03A8386');

        $tableMailboxEmail->removeForeignKey('FK_6DAF24B4896DBBDE');
        $tableMailboxEmail->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_6DAF24B4896DBBDE');

        $tableMessage = $schema->getTable('message');
        $tableMessage->removeForeignKey('FK_B6BD307F896DBBDE');
        $tableMessage->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_B6BD307F896DBBDE');

        $tableMessage->removeForeignKey('FK_B6BD307FB03A8386');
        $tableMessage->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_B6BD307FB03A8386');

        $tableMessageDocument = $schema->getTable('message_document');
        $tableMessageDocument->removeForeignKey('FK_D14F4E67B03A8386');
        $tableMessageDocument->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_D14F4E67B03A8386');

        $tableMessageDocument->removeForeignKey('FK_D14F4E67896DBBDE');
        $tableMessageDocument->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_D14F4E67896DBBDE');

        $tableOrganization = $schema->getTable('organization');
        $tableOrganization->removeForeignKey('FK_C1EE637CB03A8386');
        $tableOrganization->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_C1EE637CB03A8386');

        $tableOrganization->removeForeignKey('FK_C1EE637C896DBBDE');
        $tableOrganization->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_C1EE637C896DBBDE');

        $tableRole = $schema->getTable('role');
        $tableRole->removeForeignKey('FK_57698A6AB03A8386');
        $tableRole->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_57698A6AB03A8386');

        $tableRole->removeForeignKey('FK_57698A6A896DBBDE');
        $tableRole->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_57698A6A896DBBDE');

        $tableTeam = $schema->getTable('team');
        $tableTeam->removeForeignKey('FK_C4E0A61FB03A8386');
        $tableTeam->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_C4E0A61FB03A8386');

        $tableTeam->removeForeignKey('FK_C4E0A61F896DBBDE');
        $tableTeam->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_C4E0A61F896DBBDE');

        $tableTeamAuthorization = $schema->getTable('team_authorization');
        $tableTeamAuthorization->removeForeignKey('FK_F0FAE7EB03A8386');
        $tableTeamAuthorization->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_F0FAE7EB03A8386');

        $tableTeamAuthorization->removeForeignKey('FK_F0FAE7E896DBBDE');
        $tableTeamAuthorization->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_F0FAE7E896DBBDE');

        $tableTimeSpent = $schema->getTable('time_spent');
        $tableTimeSpent->removeForeignKey('FK_B417D625B03A8386');
        $tableTimeSpent->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_B417D625B03A8386');

        $tableTimeSpent->removeForeignKey('FK_B417D625896DBBDE');
        $tableTimeSpent->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_B417D625896DBBDE');

        $tableToken = $schema->getTable('token');
        $tableToken->removeForeignKey('FK_5F37A13BB03A8386');
        $tableToken->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_5F37A13BB03A8386');

        $tableToken->removeForeignKey('FK_5F37A13B896DBBDE');
        $tableToken->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_5F37A13B896DBBDE');

        $tableUser = $schema->getTable('users');
        $tableUser->removeForeignKey('FK_1483A5E9B03A8386');
        $tableUser->addForeignKeyConstraint('users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_1483A5E9B03A8386');

        $tableUser->removeForeignKey('FK_1483A5E9896DBBDE');
        $tableUser->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_1483A5E9896DBBDE');
    }

    public function down(Schema $schema): void
    {
        $tableTicket = $schema->getTable('ticket');
        $tableTicket->removeForeignKey('FK_97A0ADA359EC7D60');
        $tableTicket->addForeignKeyConstraint('users', ['assignee_id'], ['id'], [], 'FK_97A0ADA359EC7D60');

        $tableTicket->removeForeignKey('FK_97A0ADA3B03A8386');
        $tableTicket->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_97A0ADA3B03A8386');

        $tableTicket->removeForeignKey('FK_97A0ADA3896DBBDE');
        $tableTicket->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_97A0ADA3896DBBDE');

        $tableTicket->removeForeignKey('FK_97A0ADA3ED442CF4');
        $tableTicket->addForeignKeyConstraint('users', ['requester_id'], ['id'], [], 'FK_97A0ADA3ED442CF4');

        $tableEntityEvent = $schema->getTable('entity_event');
        $tableEntityEvent->removeForeignKey('FK_975A3F5EB03A8386');
        $tableEntityEvent->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_975A3F5EB03A8386');

        $tableEntityEvent->removeForeignKey('FK_975A3F5E896DBBDE');
        $tableEntityEvent->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_975A3F5E896DBBDE');

        $tableAuthorization = $schema->getTable('authorizations');
        $tableAuthorization->removeForeignKey('FK_2BC15D69B03A8386');
        $tableAuthorization->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_2BC15D69B03A8386');

        $tableAuthorization->removeForeignKey('FK_2BC15D69896DBBDE');
        $tableAuthorization->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_2BC15D69896DBBDE');

        $tableAuthorization->removeForeignKey('FK_2BC15D69DEEE62D0');
        $tableAuthorization->addForeignKeyConstraint('users', ['holder_id'], ['id'], [], 'FK_2BC15D69DEEE62D0');

        $tableContract = $schema->getTable('contract');
        $tableContract->removeForeignKey('FK_E98F2859B03A8386');
        $tableContract->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_E98F2859B03A8386');

        $tableContract->removeForeignKey('FK_E98F2859896DBBDE');
        $tableContract->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_E98F2859896DBBDE');

        $tableLabel = $schema->getTable('label');
        $tableLabel->removeForeignKey('FK_EA750E8B03A8386');
        $tableLabel->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_EA750E8B03A8386');

        $tableLabel->removeForeignKey('FK_EA750E8896DBBDE');
        $tableLabel->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_EA750E8896DBBDE');

        $tableMailbox = $schema->getTable('mailbox');
        $tableMailbox->removeForeignKey('FK_A69FE20BB03A8386');
        $tableMailbox->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_A69FE20BB03A8386');

        $tableMailbox->removeForeignKey('FK_A69FE20B896DBBDE');
        $tableMailbox->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_A69FE20B896DBBDE');

        $tableMailboxEmail = $schema->getTable('mailbox_email');
        $tableMailboxEmail->removeForeignKey('FK_6DAF24B4B03A8386');
        $tableMailboxEmail->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_6DAF24B4B03A8386');

        $tableMailboxEmail->removeForeignKey('FK_6DAF24B4896DBBDE');
        $tableMailboxEmail->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_6DAF24B4896DBBDE');

        $tableMessage = $schema->getTable('message');
        $tableMessage->removeForeignKey('FK_B6BD307F896DBBDE');
        $tableMessage->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_B6BD307F896DBBDE');

        $tableMessage->removeForeignKey('FK_B6BD307FB03A8386');
        $tableMessage->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_B6BD307FB03A8386');

        $tableMessageDocument = $schema->getTable('message_document');
        $tableMessageDocument->removeForeignKey('FK_D14F4E67B03A8386');
        $tableMessageDocument->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_D14F4E67B03A8386');

        $tableMessageDocument->removeForeignKey('FK_D14F4E67896DBBDE');
        $tableMessageDocument->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_D14F4E67896DBBDE');

        $tableOrganization = $schema->getTable('organization');
        $tableOrganization->removeForeignKey('FK_C1EE637CB03A8386');
        $tableOrganization->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_C1EE637CB03A8386');

        $tableOrganization->removeForeignKey('FK_C1EE637C896DBBDE');
        $tableOrganization->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_C1EE637C896DBBDE');

        $tableRole = $schema->getTable('role');
        $tableRole->removeForeignKey('FK_57698A6AB03A8386');
        $tableRole->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_57698A6AB03A8386');

        $tableRole->removeForeignKey('FK_57698A6A896DBBDE');
        $tableRole->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_57698A6A896DBBDE');

        $tableTeam = $schema->getTable('team');
        $tableTeam->removeForeignKey('FK_C4E0A61FB03A8386');
        $tableTeam->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_C4E0A61FB03A8386');

        $tableTeam->removeForeignKey('FK_C4E0A61F896DBBDE');
        $tableTeam->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_C4E0A61F896DBBDE');

        $tableTeamAuthorization = $schema->getTable('team_authorization');
        $tableTeamAuthorization->removeForeignKey('FK_F0FAE7EB03A8386');
        $tableTeamAuthorization->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_F0FAE7EB03A8386');

        $tableTeamAuthorization->removeForeignKey('FK_F0FAE7E896DBBDE');
        $tableTeamAuthorization->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_F0FAE7E896DBBDE');

        $tableTimeSpent = $schema->getTable('time_spent');
        $tableTimeSpent->removeForeignKey('FK_B417D625B03A8386');
        $tableTimeSpent->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_B417D625B03A8386');

        $tableTimeSpent->removeForeignKey('FK_B417D625896DBBDE');
        $tableTimeSpent->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_B417D625896DBBDE');

        $tableToken = $schema->getTable('token');
        $tableToken->removeForeignKey('FK_5F37A13BB03A8386');
        $tableToken->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_5F37A13BB03A8386');

        $tableToken->removeForeignKey('FK_5F37A13B896DBBDE');
        $tableToken->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_5F37A13B896DBBDE');

        $tableUser = $schema->getTable('users');
        $tableUser->removeForeignKey('FK_1483A5E9B03A8386');
        $tableUser->addForeignKeyConstraint('users', ['created_by_id'], ['id'], [], 'FK_1483A5E9B03A8386');

        $tableUser->removeForeignKey('FK_1483A5E9896DBBDE');
        $tableUser->addForeignKeyConstraint('users', ['updated_by_id'], ['id'], [], 'FK_1483A5E9896DBBDE');
    }
}
