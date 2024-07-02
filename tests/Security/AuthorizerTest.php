<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Security;

use App\Entity\Authorization;
use App\Repository\AuthorizationRepository;
use App\Security\Authorizer;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\RoleFactory;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthorizerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    private Authorizer $authorizer;

    private AuthorizationRepository $authRepository;

    #[Before]
    public function setupTest(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        /** @var Authorizer */
        $authorizer = $container->get(Authorizer::class);
        $this->authorizer = $authorizer;

        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var AuthorizationRepository */
        $authRepository = $entityManager->getRepository(Authorization::class);
        $this->authRepository = $authRepository;
    }

    public function testIsAgentWithAgentRole(): void
    {
        $user = UserFactory::createOne()->_real();
        $this->client->loginUser($user);
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ])->_real();
        $this->authRepository->grant($user, $role, null);

        $isAgent = $this->authorizer->isAgent('any');

        $this->assertTrue($isAgent);
    }

    public function testIsAgentWithAgentRoleAndScope(): void
    {
        $user = UserFactory::createOne()->_real();
        $this->client->loginUser($user);
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ])->_real();
        $organization1 = OrganizationFactory::createOne()->_real();
        $organization2 = OrganizationFactory::createOne()->_real();
        $this->authRepository->grant($user, $role, $organization1);

        $isAgent = $this->authorizer->isAgent('any');
        $this->assertTrue($isAgent);

        $isAgent = $this->authorizer->isAgent($organization1);
        $this->assertTrue($isAgent);

        $isAgent = $this->authorizer->isAgent($organization2);
        $this->assertFalse($isAgent);
    }

    public function testIsAgentWithUserRole(): void
    {
        $user = UserFactory::createOne()->_real();
        $this->client->loginUser($user);
        $role = RoleFactory::createOne([
            'type' => 'user',
        ])->_real();
        $this->authRepository->grant($user, $role, null);

        $isAgent = $this->authorizer->isAgent('any');

        $this->assertFalse($isAgent);
    }

    public function testIsAgentWithNoAuthorization(): void
    {
        $user = UserFactory::createOne()->_real();
        $this->client->loginUser($user);
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $isAgent = $this->authorizer->isAgent('any');

        $this->assertFalse($isAgent);
    }

    public function testIsAgentIfNotConnected(): void
    {
        $user = UserFactory::createOne()->_real();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ])->_real();
        $this->authRepository->grant($user, $role, null);

        $isAgent = $this->authorizer->isAgent('any');

        $this->assertFalse($isAgent);
    }
}
