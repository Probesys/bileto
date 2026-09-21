<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ArchivesControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsArchivedOrganizationsTheUserCanManage(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $orga1 = OrganizationFactory::createOne([
            'name' => 'archived-visible',
            'archivedAt' => Utils\Time::ago(2, 'days'),
        ]);
        $orga2 = OrganizationFactory::createOne([
            'name' => 'archived-hidden',
            'archivedAt' => Utils\Time::ago(2, 'days'),
        ]);
        OrganizationFactory::createOne([
            'name' => 'active-one',
        ]);
        $this->grantOrga($user->_real(), ['orga:see', 'orga:manage:archive'], $orga1->_real());
        $this->grantOrga($user->_real(), ['orga:see'], $orga2->_real());

        $client->request(Request::METHOD_GET, '/organizations/archived');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Archived organizations');
        $this->assertSelectorTextContains(
            '[data-test="archived-organization-item"]',
            'archived-visible'
        );
        $this->assertSelectorNotExists('[data-test="archived-organization-item"]:nth-child(2)');
    }

    public function testGetIndexHidesOrganizationsWithoutArchivePermission(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        OrganizationFactory::createOne([
            'name' => 'archived-one',
            'archivedAt' => Utils\Time::ago(2, 'days'),
        ]);

        $client->request(Request::METHOD_GET, '/organizations/archived');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-test="archived-organization-item"]');
    }

    public function testGetIndexIsReachableWithoutAnyPermission(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_GET, '/organizations/archived');

        $this->assertResponseIsSuccessful();
    }
}
