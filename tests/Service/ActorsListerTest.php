<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\AuthorizationRepository;
use App\Service\ActorsLister;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
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

    /**
     * @before
     */
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var ActorsLister */
        $actorsLister = $container->get(ActorsLister::class);
        $this->actorsLister = $actorsLister;

        $this->currentUser = UserFactory::createOne()->object();
        $client->loginUser($this->currentUser);

        /** @var \Zenstruck\Foundry\RepositoryProxy<AuthorizationRepository> */
        $authRepositoryProxy = AuthorizationFactory::repository();
        /** @var AuthorizationRepository */
        $authRepository = $authRepositoryProxy->inner();
        $this->authRepository = $authRepository;
    }

    public function testFindAll(): void
    {
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => RoleFactory::faker()->randomElement(['orga:tech', 'orga:user']),
        ]);
        // The current user must have an authorization on the users'
        // organizations that we want to list.
        $this->authRepository->grant(
            $this->currentUser,
            $role->object(),
            null,
        );
        // The listed users must have authorizations on some organizations
        // themselves. Otherwise, they are not part of any organization and we
        // don't want to list them.
        $this->authRepository->grant(
            $otherUser->object(),
            $role->object(),
            null,
        );

        $users = $this->actorsLister->findAll();

        $this->assertSame(2, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertContains($this->currentUser->getId(), $userIds);
        $this->assertContains($otherUser->getId(), $userIds);
    }

    public function testFindAllListsUsersInSubOrganizations(): void
    {
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => RoleFactory::faker()->randomElement(['orga:tech', 'orga:user']),
        ]);
        $organization = OrganizationFactory::createOne();
        $subOrganization = OrganizationFactory::createOne([
            'parentsPath' => "/{$organization->getId()}/",
        ]);
        $this->authRepository->grant(
            $this->currentUser,
            $role->object(),
            $organization->object(),
        );
        // Both users are not in the same organization, but the $otherUser is
        // in a sub-organization of the one of the current user.
        $this->authRepository->grant(
            $otherUser->object(),
            $role->object(),
            $subOrganization->object(),
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
            'type' => RoleFactory::faker()->randomElement(['orga:tech', 'orga:user']),
        ]);
        $organization = OrganizationFactory::createOne();
        $otherOrga = OrganizationFactory::createOne();
        $this->authRepository->grant(
            $this->currentUser,
            $role->object(),
            $organization->object(),
        );
        // The $otherUser is now in a totally separated organization.
        $this->authRepository->grant(
            $otherUser->object(),
            $role->object(),
            $otherOrga->object(),
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
            'type' => 'orga:user',
        ]);
        $roleTech = RoleFactory::createOne([
            'type' => 'orga:tech',
        ]);
        // $currentUser has a "tech" role
        $this->authRepository->grant(
            $this->currentUser,
            $roleTech->object(),
            null,
        );
        // $otherUser has a "user" role
        $this->authRepository->grant(
            $otherUser->object(),
            $roleUser->object(),
            null,
        );

        // and we ask for tech actors only
        $users = $this->actorsLister->findAll(role: 'tech');

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
            'type' => RoleFactory::faker()->randomElement(['orga:tech', 'orga:user']),
        ]);
        // The $currentUser must have an authorization on the requested
        // organization.
        $this->authRepository->grant(
            $this->currentUser,
            $role->object(),
            $organization->object(),
        );
        $this->authRepository->grant(
            $otherUser->object(),
            $role->object(),
            $organization->object(),
        );

        $users = $this->actorsLister->findByOrganization($organization->object());

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
            'type' => 'orga:user',
        ]);
        $roleTech = RoleFactory::createOne([
            'type' => 'orga:tech',
        ]);
        $this->authRepository->grant(
            $this->currentUser,
            $roleTech->object(),
            $organization->object(),
        );
        $this->authRepository->grant(
            $otherUser->object(),
            $roleUser->object(),
            $organization->object(),
        );

        $users = $this->actorsLister->findByOrganization($organization->object(), role: 'user');

        $this->assertSame(1, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertNotContains($this->currentUser->getId(), $userIds);
        $this->assertContains($otherUser->getId(), $userIds);
    }

    public function testFindByOrganizationListsUsersInParentOrganizations(): void
    {
        $organization = OrganizationFactory::createOne();
        $subOrganization = OrganizationFactory::createOne([
            'parentsPath' => "/{$organization->getId()}/",
        ]);
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => RoleFactory::faker()->randomElement(['orga:tech', 'orga:user']),
        ]);
        // $currentUser is in the parent organization
        $this->authRepository->grant(
            $this->currentUser,
            $role->object(),
            $organization->object(),
        );
        // $otherUser is in the sub-organization
        $this->authRepository->grant(
            $otherUser->object(),
            $role->object(),
            $subOrganization->object(),
        );

        // and we ask for the users of the sub-organization
        $users = $this->actorsLister->findByOrganization($subOrganization->object());

        // but both users are returned!
        $this->assertSame(2, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertContains($this->currentUser->getId(), $userIds);
        $this->assertContains($otherUser->getId(), $userIds);
    }

    public function testFindByOrganizationDoesNotListUsersIfNotAuthorized(): void
    {
        $organization = OrganizationFactory::createOne();
        $otherOrganization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => RoleFactory::faker()->randomElement(['orga:tech', 'orga:user']),
        ]);
        $this->authRepository->grant(
            $this->currentUser,
            $role->object(),
            $organization->object(),
        );
        $this->authRepository->grant(
            $otherUser->object(),
            $role->object(),
            $otherOrganization->object(),
        );

        $users = $this->actorsLister->findByOrganization($otherOrganization->object());

        $this->assertSame(0, count($users));
    }

    public function testFindByOrganizationDoesNotListUsersInParentOrganizationsIfNotAuthorized(): void
    {
        $organization = OrganizationFactory::createOne();
        $subOrganization = OrganizationFactory::createOne([
            'parentsPath' => "/{$organization->getId()}/",
        ]);
        $otherUser = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => RoleFactory::faker()->randomElement(['orga:tech', 'orga:user']),
        ]);
        // This time, $currentUser is in the sub-organization
        $this->authRepository->grant(
            $this->currentUser,
            $role->object(),
            $subOrganization->object(),
        );
        // While $otherUser is in the parent organization
        $this->authRepository->grant(
            $otherUser->object(),
            $role->object(),
            $organization->object(),
        );

        // And we ask for the users of the sub-organization
        $users = $this->actorsLister->findByOrganization($subOrganization->object());

        // Only the user from the sub-organization is returned as current user
        // isn't authorized in the parent organization
        $this->assertSame(1, count($users));
        $userIds = array_map(fn ($user): int => $user->getId(), $users);
        $this->assertContains($this->currentUser->getId(), $userIds);
        $this->assertNotContains($otherUser->getId(), $userIds);
    }
}
