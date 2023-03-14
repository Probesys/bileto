<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Command;

use App\Repository\AuthorizationRepository;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\RoleRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Utils\Random;
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
    private string $environment;

    private EntityManagerInterface $entityManager;

    private AuthorizationRepository $authorizationRepository;

    private MessageRepository $messageRepository;

    private OrganizationRepository $orgaRepository;

    private RoleRepository $roleRepository;

    private TicketRepository $ticketRepository;

    private UserRepository $userRepository;

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        string $environment,
        EntityManagerInterface $entityManager,
        AuthorizationRepository $authorizationRepository,
        MessageRepository $messageRepository,
        OrganizationRepository $orgaRepository,
        RoleRepository $roleRepository,
        TicketRepository $ticketRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ) {
        $this->environment = $environment;

        $this->entityManager = $entityManager;

        $this->authorizationRepository = $authorizationRepository;
        $this->messageRepository = $messageRepository;
        $this->orgaRepository = $orgaRepository;
        $this->roleRepository = $roleRepository;
        $this->ticketRepository = $ticketRepository;
        $this->userRepository = $userRepository;

        $this->passwordHasher = $passwordHasher;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Seed roles (for both development and production environments)
        $roleSuper = $this->roleRepository->findOrCreateSuperRole();

        $roleTech = $this->roleRepository->findOneOrCreateBy([
            'name' => 'Technician',
        ], [
            'description' => 'Solve problems.',
            'type' => 'orga',
            'permissions' => [
                'orga:create:tickets',
                'orga:create:tickets:messages',
                'orga:create:tickets:messages:confidential',
                'orga:see',
                'orga:see:tickets:all',
                'orga:see:tickets:messages:confidential',
                'orga:update:tickets:actors',
                'orga:update:tickets:priority',
                'orga:update:tickets:status',
                'orga:update:tickets:title',
                'orga:update:tickets:type',
            ],
        ]);

        $roleUser = $this->roleRepository->findOneOrCreateBy([
            'name' => 'User',
        ], [
            'description' => 'Have problems.',
            'type' => 'orga',
            'permissions' => [
                'orga:create:tickets',
                'orga:create:tickets:messages',
                'orga:see',
                'orga:update:tickets:title',
            ],
        ]);

        if ($this->environment === 'dev' || $this->environment === 'test') {
            // Seed organizations
            $orgaProbesys = $this->orgaRepository->findOneOrCreateBy([
                'name' => 'Probesys',
            ]);

            $orgaWebDivision = $this->orgaRepository->findOneOrCreateBy([
                'name' => 'Web team',
                'parentsPath' => "/{$orgaProbesys->getId()}/",
            ]);

            $orgaNetworkDivision = $this->orgaRepository->findOneOrCreateBy([
                'name' => 'Network team',
                'parentsPath' => "/{$orgaProbesys->getId()}/",
            ]);

            $orgaFriendlyCoorp = $this->orgaRepository->findOneOrCreateBy([
                'name' => 'Friendly Coorp',
            ]);

            // Seed users and authorizations
            $password = Random::hex(50);
            $userAlix = $this->userRepository->findOneOrCreateBy([
                'email' => 'alix@example.com',
            ], [
                'name' => 'Alix Hambourg',
                'password' => $password,
            ]);

            $userBenedict = $this->userRepository->findOneOrCreateBy([
                'email' => 'benedict@example.com',
            ], [
                'name' => 'Benedict Aphone',
                'password' => $password,
            ]);

            $userCharlie = $this->userRepository->findOneOrCreateBy([
                'email' => 'charlie@example.com',
            ], [
                'name' => 'Charlie Gature',
                'password' => $password,
            ]);

            foreach ([$userAlix, $userBenedict, $userCharlie] as $user) {
                if ($user->getPassword() === $password) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, 'secret'));
                    $this->userRepository->save($user);
                }
            }

            if (
                $userAlix->getId() &&
                !$this->authorizationRepository->getAdminAuthorizationFor($userAlix)
            ) {
                $this->authorizationRepository->grant($userAlix, $roleSuper);
            }

            if (
                $userAlix->getId() &&
                !$this->authorizationRepository->getOrgaAuthorizationFor($userAlix, null)
            ) {
                $this->authorizationRepository->grant($userAlix, $roleTech, null);
            }

            if (
                $userBenedict->getId() &&
                !$this->authorizationRepository->getOrgaAuthorizationFor($userBenedict, $orgaWebDivision)
            ) {
                $this->authorizationRepository->grant($userBenedict, $roleUser, $orgaWebDivision);
            }

            if (
                $userCharlie->getId() &&
                !$this->authorizationRepository->getOrgaAuthorizationFor($userCharlie, $orgaFriendlyCoorp)
            ) {
                $this->authorizationRepository->grant($userCharlie, $roleUser, $orgaFriendlyCoorp);
            }

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
                'organization' => $orgaFriendlyCoorp,
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
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Evil Corp is rejecting our emails again!!</p>
                HTML,
                'isConfidential' => true,
                'ticket' => $ticketEmails,
                'createdBy' => $userAlix,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>Thanks for the notice, we’re working on it!</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketEmails,
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
                'organization' => $orgaFriendlyCoorp,
                'requester' => $userCharlie,
                'assignee' => $userAlix,
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>It could be nice to update Bileto to the version 1.0 on the server.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketUpdate,
                'createdBy' => $userCharlie,
                'via' => 'webapp',
            ]);

            $this->messageRepository->findOneOrCreateBy([
                'content' => <<<HTML
                    <p>This is planned for tomorrow morning.</p>
                HTML,
                'isConfidential' => false,
                'ticket' => $ticketUpdate,
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
                'organization' => $orgaWebDivision,
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
                'createdBy' => $userBenedict,
                'via' => 'webapp',
            ]);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
