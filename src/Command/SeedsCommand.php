<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command;

use App\Entity;
use App\Repository;
use App\Security;
use App\Service;
use App\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'db:seeds:load',
    description: 'Load seeds in database.',
)]
class SeedsCommand
{
    public function __construct(
        private string $environment,
        private EntityManagerInterface $entityManager,
        private Repository\AuthorizationRepository $authorizationRepository,
        private Repository\MailboxRepository $mailboxRepository,
        private Repository\MessageRepository $messageRepository,
        private Repository\OrganizationRepository $orgaRepository,
        private Repository\RoleRepository $roleRepository,
        private Repository\TicketRepository $ticketRepository,
        private Repository\UserRepository $userRepository,
        private Security\Authorizer $authorizer,
        private Security\Encryptor $encryptor,
        private Service\Locales $locales,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(): int
    {
        // Seed roles (for both development and production environments)
        $roleSuper = $this->roleRepository->findOrCreateSuperRole();

        if ($this->roleRepository->count([]) > 1 && $this->environment === 'prod') {
            return Command::SUCCESS;
        }

        if ($this->environment === 'dev') {
            // In dev, give all "agent" permissions to technicians so it's
            // easier to work with.
            $techPermissions = Entity\Role::PERMISSIONS['agent'];
        } else {
            $techPermissions = [
                'orga:create:tickets',
                'orga:create:tickets:messages',
                'orga:create:tickets:messages:confidential',
                'orga:create:tickets:time_spent',
                'orga:manage',
                'orga:see',
                'orga:see:tickets:all',
                'orga:see:tickets:contracts',
                'orga:see:tickets:messages:confidential',
                'orga:see:tickets:time_spent:accounted',
                'orga:see:tickets:time_spent:real',
                'orga:update:tickets:actors',
                'orga:update:tickets:labels',
                'orga:update:tickets:organization',
                'orga:update:tickets:priority',
                'orga:update:tickets:status',
                'orga:update:tickets:title',
                'orga:update:tickets:type',
            ];
        }

        $roleTech = $this->roleRepository->findOneOrCreateBy([
            'name' => 'Technician',
        ], [
            'description' => 'Solve problems.',
            'type' => 'agent',
            'permissions' => $techPermissions,
        ]);

        $roleSalesman = $this->roleRepository->findOneOrCreateBy([
            'name' => 'Salesman',
        ], [
            'description' => 'Manage the contracts.',
            'type' => 'agent',
            'permissions' => [
                'orga:create:tickets',
                'orga:create:tickets:messages',
                'orga:create:tickets:messages:confidential',
                'orga:create:tickets:time_spent',
                'orga:manage:contracts',
                'orga:see',
                'orga:see:contracts',
                'orga:see:contracts:notes',
                'orga:see:tickets:all',
                'orga:see:tickets:contracts',
                'orga:see:tickets:messages:confidential',
                'orga:see:tickets:time_spent:accounted',
                'orga:see:tickets:time_spent:real',
                'orga:update:tickets:contracts',
            ],
        ]);

        $roleUser = $this->roleRepository->findOneOrCreateBy([
            'name' => 'User',
        ], [
            'description' => 'Have problems.',
            'type' => 'user',
            'isDefault' => true,
            'permissions' => [
                'orga:create:tickets',
                'orga:create:tickets:messages',
                'orga:see',
                'orga:see:tickets:contracts',
                'orga:see:tickets:time_spent:accounted',
                'orga:update:tickets:title',
            ],
        ]);

        if ($this->environment === 'dev' || $this->environment === 'test') {
            // Seed organizations
            $orgaProbesys = $this->orgaRepository->findOneOrCreateBy([
                'name' => 'Probesys',
            ], [
                'domains' => ['example.com'],
            ]);

            $orgaFriendlyCoop = $this->orgaRepository->findOneOrCreateBy([
                'name' => 'Friendly Coop',
            ], [
                'domains' => ['*'],
            ]);

            // Seed users and authorizations
            $userAlix = $this->userRepository->findOneOrCreateBy([
                'email' => 'alix@example.com',
            ], [
                'name' => 'Alix Hambourg',
                'organization' => $orgaProbesys,
                'locale' => $this->locales->getDefaultLocale(),
            ]);

            $userBenedict = $this->userRepository->findOneOrCreateBy([
                'email' => 'benedict@example.com',
            ], [
                'name' => 'Benedict Aphone',
                'organization' => $orgaProbesys,
                'locale' => $this->locales->getDefaultLocale(),
            ]);

            $userCharlie = $this->userRepository->findOneOrCreateBy([
                'email' => 'charlie@example.com',
            ], [
                'name' => 'Charlie Gature',
                'organization' => $orgaFriendlyCoop,
                'locale' => $this->locales->getDefaultLocale(),
                'ldapIdentifier' => 'charlie',
            ]);

            foreach ([$userAlix, $userBenedict, $userCharlie] as $user) {
                if ($user->getPassword() === '') {
                    $user->setPassword($this->passwordHasher->hashPassword($user, 'secret'));
                    $this->userRepository->save($user);
                }
            }

            // Make sure that the users exist for the grant() method.
            $this->entityManager->flush();

            if (empty($this->authorizationRepository->getAdminAuthorizations($userAlix))) {
                $this->authorizer->grant($userAlix, $roleSuper);
            }

            if (empty($this->authorizationRepository->getOrgaAuthorizations($userAlix, scope: $orgaFriendlyCoop))) {
                $this->authorizer->grant($userAlix, $roleTech, null);
            }

            if (empty($this->authorizationRepository->getOrgaAuthorizations($userBenedict, scope: $orgaFriendlyCoop))) {
                $this->authorizer->grant($userBenedict, $roleSalesman, null);
            }

            if (empty($this->authorizationRepository->getOrgaAuthorizations($userCharlie, scope: $orgaFriendlyCoop))) {
                $this->authorizer->grant($userCharlie, $roleUser, $orgaFriendlyCoop);
            }

            // Seed mailboxes
            $this->mailboxRepository->findOneOrCreateBy([
                'name' => 'support@example.com',
            ], [
                'host' => 'mailserver',
                'protocol' => 'imap',
                'port' => 3143,
                'encryption' => 'none',
                'username' => 'support@example.com',
                'password' => $this->encryptor->encrypt('secret'),
                'authentication' => 'normal',
                'folder' => 'INBOX',
            ]);

            // Seed tickets
            $ticketEmails = $this->ticketRepository->findOneOrCreateBy([
                'title' => 'My emails are not received',
            ], [
                'createdBy' => $userCharlie,
                'type' => 'incident',
                'status' => 'in_progress',
                'urgency' => 'high',
                'impact' => 'medium',
                'priority' => 'high',
                'organization' => $orgaFriendlyCoop,
                'requester' => $userCharlie,
                'assignee' => $userAlix,
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Hello, when I send my email to evil.corp@example.com, I
                    receive an error concerning its delivery.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketEmails,
                'createdAt' => Utils\Time::ago(1, 'day'),
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Evil Corp is rejecting our emails again!!</p>
                HTML,
                'isConfidential' => true,
                'ticket' => $ticketEmails,
                'createdAt' => Utils\Time::ago(10, 'hours'),
                'createdBy' => $userAlix,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Thanks for the notice, we’re working on it!</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketEmails,
                'createdAt' => Utils\Time::ago(9, 'hours'),
                'createdBy' => $userAlix,
                'via' => 'webapp',
            ]);

            $ticketUpdate = $this->ticketRepository->findOneOrCreateBy([
                'title' => 'Update Bileto to v1.0',
            ], [
                'createdBy' => $userCharlie,
                'type' => 'request',
                'status' => 'planned',
                'urgency' => 'low',
                'impact' => 'medium',
                'priority' => 'low',
                'organization' => $orgaFriendlyCoop,
                'requester' => $userCharlie,
                'assignee' => $userAlix,
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>It could be nice to update Bileto to the version 1.0 on the server.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketUpdate,
                'createdAt' => Utils\Time::ago(5, 'days'),
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>This is planned for tomorrow morning.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketUpdate,
                'createdAt' => Utils\Time::now(),
                'createdBy' => $userAlix,
                'via' => 'webapp',
            ]);

            $ticketFilter = $this->ticketRepository->findOneOrCreateBy([
                'title' => '[Bileto] Allow to filter tickets',
            ], [
                'createdBy' => $userBenedict,
                'type' => 'request',
                'status' => 'new',
                'urgency' => 'medium',
                'impact' => 'medium',
                'priority' => 'medium',
                'organization' => $orgaProbesys,
                'requester' => $userBenedict,
                'assignee' => null,
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>As a <strong>user</strong>,<br>
                    I want to <strong>filter tickets by their attributes</strong>,<br>
                    so <strong>I quickly find those that interest me.</strong></p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketFilter,
                'createdAt' => Utils\Time::ago(42, 'days'),
                'createdBy' => $userBenedict,
                'via' => 'webapp',
            ]);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
