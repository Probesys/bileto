<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\SynchronizeLdap;
use App\Repository\UserRepository;
use App\Service\Ldap;
use App\Service\UserCreator;
use App\Service\UserCreatorException;
use App\Utils\ConstraintErrorsFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsMessageHandler]
class SynchronizeLdapHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Ldap $ldap,
        private UserCreator $userCreator,
        private LoggerInterface $logger,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(SynchronizeLdap $message): void
    {
        $ldapUsers = $this->ldap->listUsers();
        $userRepository = $this->entityManager->getRepository(User::class);

        $countUsers = count($ldapUsers);

        if ($countUsers === 0) {
            return;
        }

        $this->logger->notice("[SynchronizeLdap] {$countUsers} users to synchronize");

        $countCreated = 0;
        $countUpdated = 0;
        $countErrors = 0;

        foreach ($ldapUsers as $ldapUser) {
            $user = $userRepository->findOneBy([
                'ldapIdentifier' => $ldapUser->getLdapIdentifier(),
            ]);

            if (!$user) {
                $user = $userRepository->findOneBy([
                    'email' => $ldapUser->getEmail(),
                ]);
            }

            if ($user) {
                $user->setEmail($ldapUser->getEmail());
                $user->setName($ldapUser->getName());
                $user->setLdapIdentifier($ldapUser->getLdapIdentifier());

                $errors = $this->validator->validate($user);
                $errors = ConstraintErrorsFormatter::format($errors);

                if (count($errors) === 0) {
                    $this->entityManager->persist($user);

                    $countUpdated += 1;
                } else {
                    $errors = implode(' ', $errors);
                    $this->logger->error(
                        "[SynchronizeLdap] Can't sync user {$user->getLdapIdentifier()}: {$errors}"
                    );

                    $countErrors += 1;
                }
            } else {
                $user = $ldapUser;

                try {
                    $this->userCreator->createUser($user, flush: false);
                    $this->entityManager->persist($user);

                    $countCreated += 1;
                } catch (UserCreatorException $e) {
                    $errors = ConstraintErrorsFormatter::format($e->getErrors());
                    $errors = implode(' ', $errors);
                    $this->logger->warning(
                        "[SynchronizeLdap] Can't sync user {$user->getLdapIdentifier()}: {$errors}"
                    );

                    $countErrors += 1;
                }
            }
        }

        $this->entityManager->flush();

        $this->logger->notice(
            "[SynchronizeLdap] " .
            "{$countCreated} user(s) created ; " .
            "{$countUpdated} user(s) updated ; " .
            "{$countErrors} error(s)"
        );
    }
}
