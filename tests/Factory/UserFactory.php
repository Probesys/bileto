<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        return [
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'email' => self::faker()->unique()->safeEmail(),
            'password' => self::faker()->text(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);
        });
    }

    public static function class(): string
    {
        return User::class;
    }
}
