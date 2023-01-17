<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Utils\Random;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<User>
 *
 * @method static User|Proxy createOne(array $attributes = [])
 * @method static User[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static User[]|Proxy[] createSequence(array|callable $sequence)
 * @method static User|Proxy find(object|array|mixed $criteria)
 * @method static User|Proxy findOrCreate(array $attributes)
 * @method static User|Proxy first(string $sortedField = 'id')
 * @method static User|Proxy last(string $sortedField = 'id')
 * @method static User|Proxy random(array $attributes = [])
 * @method static User|Proxy randomOrCreate(array $attributes = [])
 * @method static User[]|Proxy[] all()
 * @method static User[]|Proxy[] findBy(array $attributes)
 * @method static User[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static User[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static UserRepository|RepositoryProxy repository()
 * @method User|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static User&Proxy createOne(array $attributes = [])
 * @phpstan-method static User[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static User[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static User&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static User&Proxy findOrCreate(array $attributes)
 * @phpstan-method static User&Proxy first(string $sortedField = 'id')
 * @phpstan-method static User&Proxy last(string $sortedField = 'id')
 * @phpstan-method static User&Proxy random(array $attributes = [])
 * @phpstan-method static User&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static User[]&Proxy[] all()
 * @phpstan-method static User[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static User[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static User[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method User&Proxy create(array|callable $attributes = [])
 */
final class UserFactory extends ModelFactory
{
    /** @var UserPasswordHasherInterface */
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();

        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'uid' => Random::hex(20),
            'email' => self::faker()->unique()->safeEmail(),
            'password' => self::faker()->text(),
        ];
    }

    protected function initialize(): self
    {
        return $this->afterInstantiate(function (User $user) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);
        });
    }

    protected static function getClass(): string
    {
        return User::class;
    }
}
