<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Security\Encryptor;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MailboxFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MailboxesControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

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

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:mailboxes']);

        $client->request('GET', '/mailboxes/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New mailbox');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/mailboxes/new');
    }

    public function testPostCreateCreatesTheMailboxAndRedirects(): void
    {
        $client = static::createClient();
        /** @var Encryptor */
        $encryptor = static::getContainer()->get(Encryptor::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:mailboxes']);
        $name = 'My mailbox';
        $host = 'localhost';
        $port = 993;
        $encryption = 'ssl';
        $username = 'alix';
        $password = 'secret';
        $folder = 'INBOX';

        $this->assertSame(0, MailboxFactory::count());

        $client->request('GET', '/mailboxes/new');
        $crawler = $client->submitForm('form-create-mailbox-submit', [
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'folder' => $folder,
        ]);

        $this->assertSame(1, MailboxFactory::count());
        $this->assertResponseRedirects('/mailboxes', 302);
        $mailbox = MailboxFactory::last();
        $this->assertSame($name, $mailbox->getName());
        $this->assertSame($host, $mailbox->getHost());
        $this->assertSame($port, $mailbox->getPort());
        $this->assertSame($encryption, $mailbox->getEncryption());
        $this->assertSame($username, $mailbox->getUsername());
        $this->assertSame($password, $encryptor->decrypt($mailbox->getPassword()));
        $this->assertSame($folder, $mailbox->getFolder());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        /** @var Encryptor */
        $encryptor = static::getContainer()->get(Encryptor::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:mailboxes']);
        $name = 'My mailbox';
        $host = 'localhost';
        $port = 993;
        $encryption = 'ssl';
        $username = 'alix';
        $password = 'secret';
        $folder = 'INBOX';

        $client->request('POST', '/mailboxes/new', [
            '_csrf_token' => 'not a token',
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'folder' => $folder,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $this->assertSame(0, MailboxFactory::count());
    }

    public function testPostCreateFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        /** @var Encryptor */
        $encryptor = static::getContainer()->get(Encryptor::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:mailboxes']);
        $name = '';
        $host = 'localhost';
        $port = 993;
        $encryption = 'ssl';
        $username = 'alix';
        $password = 'secret';
        $folder = 'INBOX';

        $client->request('POST', '/mailboxes/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create mailbox'),
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'folder' => $folder,
        ]);

        $this->assertSelectorTextContains('#name-error', 'Enter a name for the mailbox.');
        $this->assertSame(0, MailboxFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        /** @var Encryptor */
        $encryptor = static::getContainer()->get(Encryptor::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My mailbox';
        $host = 'localhost';
        $port = 993;
        $encryption = 'ssl';
        $username = 'alix';
        $password = 'secret';
        $folder = 'INBOX';

        $client->catchExceptions(false);
        $client->request('POST', '/mailboxes/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create mailbox'),
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'folder' => $folder,
        ]);
    }
}
