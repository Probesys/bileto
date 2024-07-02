<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Entity\Authorization;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use App\Service\ActorsLister;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ActorsListerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    private ActorsLister $actorsLister;

    private User $currentUser;

    private AuthorizationRepository $authRepository;

    #[Before]
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var ActorsLister */
        $actorsLister = $container->get(ActorsLister::class);
        $this->actorsLister = $actorsLister;

        $this->currentUser = UserFactory::createOne()->_real();
        $client->loginUser($this->currentUser);

        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var AuthorizationRepository */
        $authRepository = $entityManager->getRepository(Authorization::class);
        $this->authRepository = $authRepository;
    }

    public function testFindAll(): void
    {
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => \Zenstruck\Foundry\faker()->randomElement(['agent', 'user']),
        ]);
        // The current user must have an authorization on the users'
        // organizations that we want to list.
        $this->authRepository->grant(
            $this->currentUser,
            $role->_real(),
            null,
        );
        // The listed users must have authorizations on some organizations
        // themselves. Otherwise, they are not part of any organization and we
        // don't want to list them.
        $this->authRepository->grant(
            $otherUser->_real(),
            $role->_real(),
            null,
        );

        $users = $this->actorsLister->findAll();

        $this->assertSame(2, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertContains($this->currentUser->getId(), $userIds);
        $this->assertContains($otherUser->getId(), $userIds);
    }

    public function testFindAllDoesNotListUsersInNotAuthorizedOrganization(): void
    {
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => \Zenstruck\Foundry\faker()->randomElement(['agent', 'user']),
        ]);
        $organization = OrganizationFactory::createOne();
        $otherOrga = OrganizationFactory::createOne();
        $this->authRepository->grant(
            $this->currentUser,
            $role->_real(),
            $organization->_real(),
        );
        // The $otherUser is now in a totally separated organization.
        $this->authRepository->grant(
            $otherUser->_real(),
            $role->_real(),
            $otherOrga->_real(),
        );

        $users = $this->actorsLister->findAll();

        $this->assertSame(1, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertContains($this->currentUser->getId(), $userIds);
        $this->assertNotContains($otherUser->getId(), $userIds);
    }

    public function testFindAllOnlyListsUsersOfTheGivenRole(): void
    {
        $otherUser = UserFactory::createOne();
        $roleUser = RoleFactory::createOne([
            'type' => 'user',
        ]);
        $roleTech = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        // $currentUser has a "tech" role
        $this->authRepository->grant(
            $this->currentUser,
            $roleTech->_real(),
            null,
        );
        // $otherUser has a "user" role
        $this->authRepository->grant(
            $otherUser->_real(),
            $roleUser->_real(),
            null,
        );

        // and we ask for tech actors only
        $users = $this->actorsLister->findAll(roleType: 'agent');

        $this->assertSame(1, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertContains($this->currentUser->getId(), $userIds);
        $this->assertNotContains($otherUser->getId(), $userIds);
    }

    public function testFindByOrganization(): void
    {
        $organization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => \Zenstruck\Foundry\faker()->randomElement(['agent', 'user']),
        ]);
        // The $currentUser must have an authorization on the requested
        // organization.
        $this->authRepository->grant(
            $this->currentUser,
            $role->_real(),
            $organization->_real(),
        );
        $this->authRepository->grant(
            $otherUser->_real(),
            $role->_real(),
            $organization->_real(),
        );

        $users = $this->actorsLister->findByOrganization($organization->_real());

        $this->assertSame(2, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertContains($this->currentUser->getId(), $userIds);
        $this->assertContains($otherUser->getId(), $userIds);
    }

    public function testFindByOrganizationOnlyListsUsersOfTheGivenRole(): void
    {
        $organization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne();
        $roleUser = RoleFactory::createOne([
            'type' => 'user',
        ]);
        $roleTech = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $this->authRepository->grant(
            $this->currentUser,
            $roleTech->_real(),
            $organization->_real(),
        );
        $this->authRepository->grant(
            $otherUser->_real(),
            $roleUser->_real(),
            $organization->_real(),
        );

        $users = $this->actorsLister->findByOrganization($organization->_real(), roleType: 'user');

        $this->assertSame(1, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertNotContains($this->currentUser->getId(), $userIds);
        $this->assertContains($otherUser->getId(), $userIds);
    }

    public function testFindByOrganizationDoesNotListUsersIfNotAuthorized(): void
    {
        $organization = OrganizationFactory::createOne();
        $otherOrganization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => \Zenstruck\Foundry\faker()->randomElement(['agent', 'user']),
        ]);
        $this->authRepository->grant(
            $this->currentUser,
            $role->_real(),
            $organization->_real(),
        );
        $this->authRepository->grant(
            $otherUser->_real(),
            $role->_real(),
            $otherOrganization->_real(),
        );

        $users = $this->actorsLister->findByOrganization($otherOrganization->_real());

        $this->assertSame(0, count($users));
    }
}
