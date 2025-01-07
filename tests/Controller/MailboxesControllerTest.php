<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Security;
use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MailboxesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetIndexListsMailboxesSortedByName(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        Factory\MailboxFactory::createOne([
            'name' => 'Mailbox 2',
        ]);
        Factory\MailboxFactory::createOne([
            'name' => 'Mailbox 1',
        ]);

        $client->request(Request::METHOD_GET, '/mailboxes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mailboxes');
        $this->assertSelectorTextContains('[data-test="mailbox-item"]:nth-child(1)', 'Mailbox 1');
        $this->assertSelectorTextContains('[data-test="mailbox-item"]:nth-child(2)', 'Mailbox 2');
    }

    public function testGetIndexListsMailboxEmailsInError(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        Factory\MailboxEmailFactory::createOne([
            'lastError' => 'unknown sender',
        ]);

        $client->request(Request::METHOD_GET, '/mailboxes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="mailbox-email-item"]', 'unknown sender');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/mailboxes');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);

        $client->request(Request::METHOD_GET, '/mailboxes/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New mailbox');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/mailboxes/new');
    }

    public function testPostNewCreatesTheMailboxAndRedirects(): void
    {
        $client = static::createClient();
        /** @var Security\Encryptor */
        $encryptor = static::getContainer()->get(Security\Encryptor::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $name = 'My mailbox';
        $host = 'localhost';
        $port = 993;
        $encryption = 'ssl';
        $username = 'alix';
        $password = 'secret';
        $folder = 'INBOX';
        $postAction = 'delete';

        $this->assertSame(0, Factory\MailboxFactory::count());

        $client->request(Request::METHOD_POST, '/mailboxes/new', [
            'mailbox' => [
                '_token' => $this->generateCsrfToken($client, 'mailbox'),
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'plainPassword' => $password,
                'folder' => $folder,
                'postAction' => $postAction,
            ],
        ]);

        $this->assertSame(1, Factory\MailboxFactory::count());
        $this->assertResponseRedirects('/mailboxes', 302);
        $mailbox = Factory\MailboxFactory::last();
        $this->assertSame($name, $mailbox->getName());
        $this->assertSame($host, $mailbox->getHost());
        $this->assertSame($port, $mailbox->getPort());
        $this->assertSame($encryption, $mailbox->getEncryption());
        $this->assertSame($username, $mailbox->getUsername());
        $this->assertSame($password, $encryptor->decrypt($mailbox->getPassword()));
        $this->assertSame($folder, $mailbox->getFolder());
        $this->assertSame($postAction, $mailbox->getPostAction());
    }

    public function testPostNewFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        /** @var Security\Encryptor */
        $encryptor = static::getContainer()->get(Security\Encryptor::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $name = '';
        $host = 'localhost';
        $port = 993;
        $encryption = 'ssl';
        $username = 'alix';
        $password = 'secret';
        $folder = 'INBOX';
        $postAction = 'delete';

        $client->request(Request::METHOD_POST, '/mailboxes/new', [
            'mailbox' => [
                '_token' => $this->generateCsrfToken($client, 'mailbox'),
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'plainPassword' => $password,
                'folder' => $folder,
                'postAction' => $postAction,
            ],
        ]);

        $this->assertSame(0, Factory\MailboxFactory::count());
        $this->assertSelectorTextContains('#mailbox_name-error', 'Enter a name for the mailbox.');
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        /** @var Security\Encryptor */
        $encryptor = static::getContainer()->get(Security\Encryptor::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $name = 'My mailbox';
        $host = 'localhost';
        $port = 993;
        $encryption = 'ssl';
        $username = 'alix';
        $password = 'secret';
        $folder = 'INBOX';
        $postAction = 'delete';

        $client->request(Request::METHOD_POST, '/mailboxes/new', [
            'mailbox' => [
                '_token' => 'not a token',
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'plainPassword' => $password,
                'folder' => $folder,
                'postAction' => $postAction,
            ],
        ]);

        $this->assertSame(0, Factory\MailboxFactory::count());
        $this->assertSelectorTextContains('#mailbox-error', 'The security token is invalid');
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $mailbox = Factory\MailboxFactory::createOne();

        $client->request(Request::METHOD_GET, "/mailboxes/{$mailbox->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit a mailbox');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $mailbox = Factory\MailboxFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/mailboxes/{$mailbox->getUid()}/edit");
    }

    public function testPostEditSavesTheMailboxAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $oldName = 'Old mailbox name';
        $newName = 'New mailbox name';
        $mailbox = Factory\MailboxFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request(Request::METHOD_POST, "/mailboxes/{$mailbox->getUid()}/edit", [
            'mailbox' => [
                '_token' => $this->generateCsrfToken($client, 'mailbox'),
                'name' => $newName,
                'host' => 'localhost',
                'port' => 993,
                'encryption' => 'ssl',
                'username' => 'alix',
                'plainPassword' => 'secret',
                'folder' => 'INBOX',
                'postAction' => 'delete',
            ],
        ]);

        $this->assertResponseRedirects("/mailboxes/{$mailbox->getUid()}/edit", 302);
        $mailbox->_refresh();
        $this->assertSame($newName, $mailbox->getName());
    }

    public function testPostEditAcceptsEmptyPassword(): void
    {
        $client = static::createClient();
        /** @var Security\Encryptor */
        $encryptor = static::getContainer()->get(Security\Encryptor::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $password = 'secret';
        $mailbox = Factory\MailboxFactory::createOne([
            'password' => $password,
        ]);

        $client->request(Request::METHOD_POST, "/mailboxes/{$mailbox->getUid()}/edit", [
            'mailbox' => [
                '_token' => $this->generateCsrfToken($client, 'mailbox'),
                'name' => 'My mailbox',
                'host' => 'localhost',
                'port' => 993,
                'encryption' => 'ssl',
                'username' => 'alix',
                'plainPassword' => '',
                'folder' => 'INBOX',
                'postAction' => 'delete',
            ],
        ]);

        $this->assertResponseRedirects("/mailboxes/{$mailbox->getUid()}/edit", 302);
        $mailbox->_refresh();
        $this->assertSame($password, $encryptor->decrypt($mailbox->getPassword()));
    }

    public function testPostEditFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $oldName = 'Old mailbox name';
        $newName = '';
        $mailbox = Factory\MailboxFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request(Request::METHOD_POST, "/mailboxes/{$mailbox->getUid()}/edit", [
            'mailbox' => [
                '_token' => $this->generateCsrfToken($client, 'mailbox'),
                'name' => $newName,
                'host' => 'localhost',
                'port' => 993,
                'encryption' => 'ssl',
                'username' => 'alix',
                'plainPassword' => 'secret',
                'folder' => 'INBOX',
                'postAction' => 'delete',
            ],
        ]);

        $this->assertSelectorTextContains('#mailbox_name-error', 'Enter a name for the mailbox.');
        $this->clearEntityManager();
        $mailbox->_refresh();
        $this->assertSame($oldName, $mailbox->getName());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $oldName = 'Old mailbox name';
        $newName = 'New mailbox name';
        $mailbox = Factory\MailboxFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request(Request::METHOD_POST, "/mailboxes/{$mailbox->getUid()}/edit", [
            'mailbox' => [
                '_token' => 'not a token',
                'name' => $newName,
                'host' => 'localhost',
                'port' => 993,
                'encryption' => 'ssl',
                'username' => 'alix',
                'plainPassword' => 'secret',
                'folder' => 'INBOX',
                'postAction' => 'delete',
            ],
        ]);

        $this->assertSelectorTextContains('#mailbox-error', 'The security token is invalid');
        $this->clearEntityManager();
        $mailbox->_refresh();
        $this->assertSame($oldName, $mailbox->getName());
    }

    public function testPostDeleteRemovesTheMailboxAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $mailbox = Factory\MailboxFactory::createOne();
        $mailboxEmail = Factory\MailboxEmailFactory::createOne([
            'mailbox' => $mailbox,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/mailboxes/{$mailbox->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete mailbox'),
        ]);

        $this->assertResponseRedirects('/mailboxes', 302);
        Factory\MailboxFactory::assert()->notExists(['id' => $mailbox->getId()]);
        Factory\MailboxEmailFactory::assert()->notExists(['id' => $mailboxEmail->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:mailboxes']);
        $mailbox = Factory\MailboxFactory::createOne();

        $client->request(Request::METHOD_POST, "/mailboxes/{$mailbox->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertResponseRedirects("/mailboxes/{$mailbox->getUid()}/edit", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        Factory\MailboxFactory::assert()->exists(['id' => $mailbox->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $mailbox = Factory\MailboxFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/mailboxes/{$mailbox->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete mailbox'),
        ]);
    }
}
