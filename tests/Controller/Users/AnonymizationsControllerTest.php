<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Users;

use App\Tests;
use App\Tests\Factory;
use App\Repository;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AnonymizationsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\SessionHelper;

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_GET, "/users/{$otherUser->getUid()}/anonymizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Anonymize the user');
    }

    public function testGetNewFailsIfUserIsAlreadyAnonymized(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne([
            'anonymizedAt' => Utils\Time::now(),
        ]);
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/users/{$otherUser->getUid()}/anonymizations/new");
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/users/{$otherUser->getUid()}/anonymizations/new");
    }

    public function testGetNewFailsIfTryingToAnonymizeCurrentUser(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/users/{$currentUser->getUid()}/anonymizations/new");
    }

    public function testPostNewAnonymizesTheUser(): void
    {
        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne([
            'name' => 'Charlie Gature',
            'email' => 'charlie@example.net',
            'organization' => Factory\OrganizationFactory::createOne(),
            'ldapIdentifier' => 'charlie.gature',
            'anonymizedAt' => null,
            'anonymizedBy' => null,
        ]);
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/anonymizations/new", [
            'anonymization' => [
                '_token' => $this->generateCsrfToken($client, 'anonymization'),
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        $otherUser->_refresh();
        $this->assertSame('Anonymous user', $otherUser->getName());
        $this->assertStringEndsWith('@example.com', $otherUser->getEmail());
        $this->assertNull($otherUser->getOrganization());
        $this->assertSame('', $otherUser->getLdapIdentifier());
        $this->assertFalse($otherUser->canLogin());
        $this->assertTrue($otherUser->isAnonymized());
        $this->assertSame($currentUser->getUid(), $otherUser->getAnonymizedBy()->getUid());
    }

    public function testPostNewRemovesTheUserFromTeams(): void
    {
        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);
        $team = Factory\TeamFactory::createOne([
            'agents' => [$otherUser],
        ]);

        $this->assertTrue($team->hasAgent($otherUser->_real()));

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/anonymizations/new", [
            'anonymization' => [
                '_token' => $this->generateCsrfToken($client, 'anonymization'),
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        $this->assertFalse($team->hasAgent($otherUser->_real()));
    }

    public function testPostNewRemovesUserAuthorizations(): void
    {
        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);
        $role = Factory\RoleFactory::createOne([
            'type' => 'admin',
        ]);
        $authorization = Factory\AuthorizationFactory::createOne([
            'holder' => $otherUser,
            'role' => $role,
        ]);

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/anonymizations/new", [
            'anonymization' => [
                '_token' => $this->generateCsrfToken($client, 'anonymization'),
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        Factory\AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostNewRemovesRelatedEntityEvents(): void
    {
        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $otherUser->setName('Charlie');
        $userRepository->save($otherUser->_real(), true);
        $lastEntityEvent = Factory\EntityEventFactory::last();
        $this->assertSame($otherUser->getEntityType(), $lastEntityEvent->getEntityType());
        $this->assertSame($otherUser->getId(), $lastEntityEvent->getEntityId());

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/anonymizations/new", [
            'anonymization' => [
                '_token' => $this->generateCsrfToken($client, 'anonymization'),
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        Factory\EntityEventFactory::assert()->notExists(['id' => $lastEntityEvent->getId()]);
    }

    public function testPostNewRemovesRelatedSessionLogs(): void
    {
        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);
        $sessionLog = Factory\SessionLogFactory::createOne([
            'identifier' => $otherUser->getUserIdentifier(),
        ]);

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/anonymizations/new", [
            'anonymization' => [
                '_token' => $this->generateCsrfToken($client, 'anonymization'),
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        Factory\SessionLogFactory::assert()->notExists(['id' => $sessionLog->getId()]);
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $currentUser = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $result = $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/anonymizations/new", [
            'anonymization' => [
                '_token' => 'not a token',
            ],
        ]);

        $this->assertSelectorTextContains('#anonymization-error', 'The security token is invalid');
        $otherUser->_refresh();
        $this->assertFalse($otherUser->isAnonymized());
        $this->assertNull($otherUser->getAnonymizedBy());
    }
}
