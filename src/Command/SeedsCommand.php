<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command;

use App\ActivityMonitor;
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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'db:seeds:load',
    description: 'Load seeds in database.',
)]
class SeedsCommand extends Command
{
    public function __construct(
        private string $environment,
        private EntityManagerInterface $entityManager,
        private Repository\AuthorizationRepository $authorizationRepository,
        private Repository\ContractRepository $contractRepository,
        private Repository\MailboxRepository $mailboxRepository,
        private Repository\MessageRepository $messageRepository,
        private Repository\OrganizationRepository $orgaRepository,
        private Repository\RoleRepository $roleRepository,
        private Repository\TicketRepository $ticketRepository,
        private Repository\TimeSpentRepository $timeSpentRepository,
        private Repository\UserRepository $userRepository,
        private Security\Authorizer $authorizer,
        private Security\Encryptor $encryptor,
        private Service\Locales $locales,
        private UserPasswordHasherInterface $passwordHasher,
        private ActivityMonitor\ActiveUser $activeUser,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

            $this->activeUser->change($userAlix);

            // Seed contracts
            $contract = new Entity\Contract();
            $contract->setName('Friendly Coop 2024');
            $contract->setStartAt(new \DateTimeImmutable('2024-01-01 00:00:00'));
            $contract->setEndAt(new \DateTimeImmutable('2024-12-31 23:59:59'));
            $contract->setMaxHours(30);
            $contract->setTimeAccountingUnit(30);
            $contract->setOrganization($orgaFriendlyCoop);
            $this->contractRepository->save($contract, true);

            // Seed tickets
            $ticketEmails = $this->ticketRepository->findOneOrCreateBy([
                'title' => 'Erreur d’envoi d’emails',
            ], [
                'createdBy' => $userCharlie,
                'createdAt' => Utils\Time::ago(1, 'day'),
                'updatedBy' => $userCharlie,
                'updatedAt' => Utils\Time::ago(5, 'hours'),
                'type' => 'incident',
                'status' => 'in_progress',
                'urgency' => 'high',
                'impact' => 'medium',
                'priority' => 'high',
                'organization' => $orgaFriendlyCoop,
                'requester' => $userCharlie,
                'assignee' => $userAlix,
                'contracts' => [$contract],
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Bonjour, lorsque j’envoie un email à evil.corp@example.com,
                    je reçois une erreur.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketEmails,
                'createdAt' => Utils\Time::ago(1, 'day'),
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Bonjour, pourriez-vous nous indiquer l’erreur que vous recevez ?</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketEmails,
                'createdAt' => Utils\Time::ago(9, 'hours'),
                'createdBy' => $userAlix,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Oui bien sûr, l’erreur est la suivante : <code>Recipient
                        address rejected: User unknown in virtual mailbox table
                        (in reply to RCPT TO command)</code>.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketEmails,
                'createdAt' => Utils\Time::ago(5, 'hours'),
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);

            $ticketConnection = $this->ticketRepository->findOneOrCreateBy([
                'title' => 'Problème connexion Bileto',
            ], [
                'createdBy' => $userCharlie,
                'createdAt' => Utils\Time::ago(4, 'days'),
                'updatedBy' => $userCharlie,
                'updatedAt' => Utils\Time::ago(3, 'days'),
                'type' => 'incident',
                'status' => 'resolved',
                'urgency' => 'medium',
                'impact' => 'medium',
                'priority' => 'medium',
                'organization' => $orgaFriendlyCoop,
                'requester' => $userCharlie,
                'assignee' => $userAlix,
                'contracts' => [$contract],
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Bonjour, je n’arrive pas à me connecter à Bileto avec mes identifiants habituels. Pouvez-vous m’aider ? Merci.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketConnection,
                'createdAt' => Utils\Time::ago(4, 'day'),
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);

            $solution = $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Bonjour, comme vu au téléphone, il vous faut réinitialiser votre mot de passe dans Bileto avant de pouvoir vous connecter. Bonne journée.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketConnection,
                'createdAt' => Utils\Time::now(),
                'createdBy' => $userAlix,
                'via' => 'webapp',
            ]);

            $timeSpent = new Entity\TimeSpent();
            $timeSpent->setTime(30);
            $timeSpent->setRealTime(30);
            $timeSpent->setContract($contract);
            $timeSpent->setTicket($ticketConnection);
            $timeSpent->setCreatedAt(Utils\Time::now());
            $timeSpent->setCreatedBy($userAlix);
            $timeSpent->setUpdatedBy($userAlix);

            $this->timeSpentRepository->save($timeSpent, true);

            $ticketConnection->setSolution($solution);
            $this->entityManager->persist($ticketConnection);

            $ticketClosed = $this->ticketRepository->findOneOrCreateBy([
                'title' => 'Besoin restauration d’un backup',
            ], [
                'createdBy' => $userCharlie,
                'createdAt' => Utils\Time::ago(10, 'days'),
                'updatedBy' => $userCharlie,
                'updatedAt' => Utils\Time::ago(9, 'days'),
                'type' => 'request',
                'status' => 'closed',
                'urgency' => 'high',
                'impact' => 'high',
                'priority' => 'high',
                'organization' => $orgaFriendlyCoop,
                'requester' => $userCharlie,
                'assignee' => $userBenedict,
                'contracts' => [$contract],
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Bonjour, j’aurais besoin que vous remettiez en place le backup du 25 novembre dernier, merci !</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketClosed,
                'createdAt' => Utils\Time::ago(10, 'day'),
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
