<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;
use App\Service;
use App\Utils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserCreator
{
    public function __construct(
        private Repository\AuthorizationRepository $authorizationRepository,
        private Repository\OrganizationRepository $organizationRepository,
        private Repository\RoleRepository $roleRepository,
        private Repository\UserRepository $userRepository,
        private Service\Locales $locales,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function createUser(Entity\User $user, bool $flush = true): void
    {
        if ($user->getLocale() === '') {
            $defaultLocale = $this->locales->getDefaultLocale();
            $user->setLocale($defaultLocale);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            throw new UserCreatorException($errors);
        }

        $this->userRepository->save($user, $flush);

        $defaultRole = $this->roleRepository->findDefault();
        if ($defaultRole) {
            $organization = $user->getOrganization();

            if ($organization) {
                $authorizationOrganization = $organization;
            } else {
                $emailDomain = Utils\Email::extractDomain($user->getEmail());
                $authorizationOrganization = $this->organizationRepository->findOneByDomainOrDefault($emailDomain);
            }

            if ($authorizationOrganization) {
                $this->authorizationRepository->grant(
                    $user,
                    $defaultRole,
                    $authorizationOrganization,
                );
            }
        }
    }

    public function create(
        string $email,
        string $name = '',
        string $password = '',
        string $locale = '',
        string $ldapIdentifier = '',
        ?Entity\Organization $organization = null,
        bool $flush = true,
    ): Entity\User {
        $user = new Entity\User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setLocale($locale);
        $user->setLdapIdentifier($ldapIdentifier);
        $user->setOrganization($organization);

        if ($password !== '') {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }

        $this->createUser($user, $flush);

        return $user;
    }
}
