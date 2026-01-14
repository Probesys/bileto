<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MailboxEmailFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MailboxEmailsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testPostDeleteRemovesTheMailboxEmailAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $mailboxEmail = MailboxEmailFactory::createOne();

        $client->request(Request::METHOD_POST, "/mailbox-emails/{$mailboxEmail->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete mailbox email'),
        ]);

        $this->assertResponseRedirects('/mailboxes', 302);
        MailboxEmailFactory::assert()->notExists(['id' => $mailboxEmail->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $mailboxEmail = MailboxEmailFactory::createOne();

        $client->request(Request::METHOD_POST, "/mailbox-emails/{$mailboxEmail->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertResponseRedirects('/mailboxes', 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        MailboxEmailFactory::assert()->exists(['id' => $mailboxEmail->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $mailboxEmail = MailboxEmailFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/mailbox-emails/{$mailboxEmail->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete mailbox email'),
        ]);
    }
}
