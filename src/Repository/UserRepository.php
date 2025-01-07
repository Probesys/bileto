<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements
    PasswordUpgraderInterface,
    UidGeneratorInterface,
    UserLoaderInterface
{
    /** @phpstan-use CommonTrait<User> */
    use CommonTrait;
    use UidGeneratorTrait;
    /** @phpstan-use FindOrCreateTrait<User> */
    use FindOrCreateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function loadUserByIdentifier(string $identifier): ?User
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT u
            FROM App\Entity\User u
            WHERE (u.ldapIdentifier = '' AND u.email = :identifier)
            OR u.ldapIdentifier = :identifier
        SQL);
        $query->setParameter('identifier', $identifier);
        return $query->getOneOrNullResult();
    }

    public function loadUserByEmail(string $email): ?User
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT u
            FROM App\Entity\User u
            WHERE u.email = :identifier
        SQL);
        $query->setParameter('identifier', $email);
        return $query->getOneOrNullResult();
    }

    /**
     * @return User[]
     */
    public function findAllWithAuthorizations(): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT u, a
            FROM App\Entity\User u
            LEFT JOIN u.authorizations a
        SQL);

        return $query->getResult();
    }

    /**
     * @return User[]
     */
    public function findLike(string $value): array
    {
        $entityManager = $this->getEntityManager();

        $value = mb_strtolower($value);

        $query = $entityManager->createQuery(<<<SQL
            SELECT u
            FROM App\Entity\User u
            WHERE LOWER(u.name) LIKE :value
            OR LOWER(u.email) LIKE :value
        SQL);
        $query->setParameter('value', "%{$value}%");

        return $query->getResult();
    }

    /**
     * @param int[] $orgaIds
     * @param 'any'|'user'|'agent' $roleType
     *
     * @return User[]
     */
    public function findByOrganizationIds(array $orgaIds, string $roleType = 'any'): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT u
            FROM App\Entity\User u
            JOIN u.authorizations a
            JOIN a.role r
            WHERE (a.organization IN (:organizations) OR a.organization IS NULL)
            AND r.type IN (:types)
        SQL);

        if ($roleType === 'any') {
            $types = ['user', 'agent'];
        } else {
            $types = [$roleType];
        }

        $query->setParameter('organizations', $orgaIds);
        $query->setParameter('types', $types);

        return $query->getResult();
    }

    public function findOneByResetPasswordToken(string $token): ?User
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT u
            FROM App\Entity\User u
            INNER JOIN u.resetPasswordToken t
            WHERE t.value = :token
        SQL);

        $query->setParameter('token', $token);

        return $query->getOneOrNullResult();
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->getEntityManager()->flush();
    }
}
