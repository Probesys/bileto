<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MailboxFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MailboxesControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetIndexListsMailboxesSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:mailboxes']);
        MailboxFactory::createOne([
            'name' => 'Mailbox 2',
        ]);
        MailboxFactory::createOne([
            'name' => 'Mailbox 1',
        ]);

        $client->request('GET', '/mailboxes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mailboxes');
        $this->assertSelectorTextContains('[data-test="mailbox-item"]:nth-child(1)', 'Mailbox 1');
        $this->assertSelectorTextContains('[data-test="mailbox-item"]:nth-child(2)', 'Mailbox 2');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/mailboxes');
    }
}
