<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Tests\AuthorizationHelper;
use App\Tests\FactoriesHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class OrganizationsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsOrganizationsSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:see']);
        OrganizationFactory::createOne([
            'name' => 'foo',
        ]);
        OrganizationFactory::createOne([
            'name' => 'bar',
        ]);
        OrganizationFactory::createOne([
            'name' => 'Baz',
        ]);

        $client->request('GET', '/organizations');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Organizations');
        $this->assertSelectorTextContains('[data-test="organization-item"]:nth-child(1)', 'bar');
        $this->assertSelectorTextContains('[data-test="organization-item"]:nth-child(2)', 'Baz');
        $this->assertSelectorTextContains('[data-test="organization-item"]:nth-child(3)', 'foo');
    }

    public function testGetIndexDoesNotListNotAuthorizedOrganizations(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $orga1 = OrganizationFactory::createOne([
            'name' => 'foo',
        ]);
        $orga2 = OrganizationFactory::createOne([
            'name' => 'bar',
        ]);
        $this->grantOrga($user->object(), ['orga:see'], $orga1->object());

        $client->request('GET', '/organizations');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Organizations');
        $this->assertSelectorTextContains('[data-test="organization-item"]', 'foo');
        $this->assertSelectorNotExists('[data-test="organization-item"]:nth-child(2)');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:create:organizations']);

        $client->request('GET', '/organizations/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New organization');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/organizations/new');
    }

    public function testPostCreateCreatesAnOrganizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:create:organizations']);
        $name = 'My organization';

        $client->request('POST', '/organizations/new', [
            'organization' => [
                '_token' => $this->generateCsrfToken($client, 'organization'),
                'name' => $name,
            ],
        ]);

        $this->assertResponseRedirects('/organizations', 302);
        $organization = OrganizationFactory::first();
        $this->assertSame($name, $organization->getName());
        $this->assertSame(20, strlen($organization->getUid()));
    }

    public function testPostCreateFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:create:organizations']);
        $name = '';

        $client->request('POST', '/organizations/new', [
            'organization' => [
                '_token' => $this->generateCsrfToken($client, 'organization'),
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains('#organization_name-error', 'Enter a name');
        $this->assertSame(0, OrganizationFactory::count());
    }

    public function testPostCreateFailsIfNameIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:create:organizations']);
        $name = str_repeat('a', 256);

        $client->request('POST', '/organizations/new', [
            'organization' => [
                '_token' => $this->generateCsrfToken($client, 'organization'),
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains('#organization_name-error', 'Enter a name of less than 255 characters');
        $this->assertSame(0, OrganizationFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:create:organizations']);
        $name = 'My organization';

        $client->request('POST', '/organizations/new', [
            'organization' => [
                '_token' => 'not a token',
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains('#organization-error', 'The security token is invalid');
        $this->assertSame(0, OrganizationFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My organization';

        $client->catchExceptions(false);
        $client->request('POST', '/organizations/new', [
            'organization' => [
                '_token' => $this->generateCsrfToken($client, 'organization'),
                'name' => $name,
            ],
        ]);
    }

    public function testGetShowRedirectsToTickets(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), ['orga:see'], $organization->object());

        $client->request('GET', "/organizations/{$organization->getUid()}");

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/tickets", 302);
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/organizations/{$organization->getUid()}");
    }

    public function testGetSettingsRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage']);
        $organization = OrganizationFactory::createOne();

        $client->request('GET', "/organizations/{$organization->getUid()}/settings");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Settings');
    }

    public function testGetSettingsFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/organizations/{$organization->getUid()}/settings");
    }

    public function testPostUpdateSavesTheOrganizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage']);
        $oldName = 'Old name';
        $newName = 'New name';
        $organization = OrganizationFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request('POST', "/organizations/{$organization->getUid()}/settings", [
            'organization' => [
                '_token' => $this->generateCsrfToken($client, 'organization'),
                'name' => $newName,
            ],
        ]);

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/settings", 302);
        $organization->refresh();
        $this->assertSame($newName, $organization->getName());
    }

    public function testPostUpdateFailsIfNameIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage']);
        $oldName = 'Old name';
        $newName = str_repeat('a', 256);
        $organization = OrganizationFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request('POST', "/organizations/{$organization->getUid()}/settings", [
            'organization' => [
                '_token' => $this->generateCsrfToken($client, 'organization'),
                'name' => $newName,
            ],
        ]);

        $this->assertSelectorTextContains('#organization_name-error', 'Enter a name of less than 255 characters');
        $organization->refresh();
        $this->assertSame($oldName, $organization->getName());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage']);
        $oldName = 'Old name';
        $newName = 'New name';
        $organization = OrganizationFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request('POST', "/organizations/{$organization->getUid()}/settings", [
            'organization' => [
                '_token' => 'not a token',
                'name' => $newName,
            ],
        ]);

        $this->assertSelectorTextContains('#organization-error', 'The security token is invalid');
        $organization->refresh();
        $this->assertSame($oldName, $organization->getName());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $oldName = 'Old name';
        $newName = 'New name';
        $organization = OrganizationFactory::createOne([
            'name' => $oldName,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/organizations/{$organization->getUid()}/settings", [
            'organization' => [
                '_token' => $this->generateCsrfToken($client, 'organization'),
                'name' => $newName,
            ],
        ]);
    }

    public function testPostDeleteRemovesTheOrganizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage']);
        $organization = OrganizationFactory::createOne();
        $authorization = AuthorizationFactory::createOne([
            'organization' => $organization,
        ]);
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
        ]);
        $message = MessageFactory::createOne([
            'ticket' => $ticket,
        ]);

        $this->clearEntityManager();

        $client->request('POST', "/organizations/{$organization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete organization'),
        ]);

        $this->assertResponseRedirects('/organizations', 302);
        OrganizationFactory::assert()->notExists(['id' => $organization->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
        TicketFactory::assert()->notExists(['id' => $ticket->getId()]);
        MessageFactory::assert()->notExists(['id' => $message->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage']);
        $organization = OrganizationFactory::createOne();

        $client->request('POST', "/organizations/{$organization->getUid()}/deletion", [
            '_csrf_token' => 'not a token',
        ]);

        $this->assertResponseRedirects('/organizations', 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        OrganizationFactory::assert()->exists(['id' => $organization->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();

        $client->catchExceptions(false);
        $client->request('POST', "/organizations/{$organization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete organization'),
        ]);
    }
}
