<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserCreator
{
    public function __construct(
        private UserRepository $userRepository,
        private ValidatorInterface $validator,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function create(
        string $email,
        string $name = '',
        string $password = '',
        string $locale = \App\Utils\Locales::DEFAULT_LOCALE,
        ?string $ldapIdentifier = null,
        ?Organization $organization = null,
        bool $flush = true,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setLocale($locale);
        $user->setLdapIdentifier($ldapIdentifier);
        $user->setOrganization($organization);

        if ($password !== '') {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            throw new UserCreatorException($errors);
        }

        $this->userRepository->save($user, $flush);

        return $user;
    }
}
