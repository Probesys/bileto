<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessageTemplatesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetIndexListsMessageTemplates(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        Factory\MessageTemplateFactory::createOne([
            'name' => 'Template 2',
            'type' => 'normal',
        ]);
        Factory\MessageTemplateFactory::createOne([
            'name' => 'Template 1',
            'type' => 'normal',
        ]);

        $client->request(Request::METHOD_GET, '/message-templates');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Answer templates');
        $this->assertSelectorTextContains('[data-test="message-template-item"]:nth-child(1)', 'Template 1');
        $this->assertSelectorTextContains('[data-test="message-template-item"]:nth-child(2)', 'Template 2');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/message-templates');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);

        $client->request(Request::METHOD_GET, '/message-templates/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New answer template');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/message-templates/new');
    }

    public function testPostNewCreatesTheMessageTemplateAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $name = 'My message template';
        $type = 'solution';
        $content = '<p>Lorem ipsum</p>';

        $this->assertSame(0, Factory\MessageTemplateFactory::count());

        $client->request(Request::METHOD_POST, '/message-templates/new', [
            'message_template' => [
                '_token' => $this->generateCsrfToken($client, 'message_template'),
                'name' => $name,
                'type' => $type,
                'content' => $content,
            ],
        ]);

        $this->assertSame(1, Factory\MessageTemplateFactory::count());
        $this->assertResponseRedirects('/message-templates', 302);
        $messageTemplate = Factory\MessageTemplateFactory::last();
        $this->assertSame($name, $messageTemplate->getName());
        $this->assertSame($type, $messageTemplate->getType());
        $this->assertSame($content, $messageTemplate->getContent());
    }

    public function testPostNewFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $name = '';
        $type = 'solution';
        $content = '<p>Lorem ipsum</p>';

        $client->request(Request::METHOD_POST, '/message-templates/new', [
            'message_template' => [
                '_token' => $this->generateCsrfToken($client, 'message_template'),
                'name' => $name,
                'type' => $type,
                'content' => $content,
            ],
        ]);

        $this->assertSame(0, Factory\MessageTemplateFactory::count());
        $this->assertSelectorTextContains('#message_template_name-error', 'Enter a name.');
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $name = 'My message template';
        $type = 'solution';
        $content = '<p>Lorem ipsum</p>';

        $client->request(Request::METHOD_POST, '/message-templates/new', [
            'message_template' => [
                '_token' => 'not a token',
                'name' => $name,
                'type' => $type,
                'content' => $content,
            ],
        ]);

        $this->assertSame(0, Factory\MessageTemplateFactory::count());
        $this->assertSelectorTextContains('#message_template-error', 'The security token is invalid');
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $messageTemplate = Factory\MessageTemplateFactory::createOne();

        $client->request(Request::METHOD_GET, "/message-templates/{$messageTemplate->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit an answer template');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $messageTemplate = Factory\MessageTemplateFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/message-templates/{$messageTemplate->getUid()}/edit");
    }

    public function testPostEditSavesTheMessageTemplateAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $oldName = 'Old template name';
        $newName = 'New template name';
        $newType = 'solution';
        $newContent = '<p>Lorem ipsum</p>';
        $messageTemplate = Factory\MessageTemplateFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request(Request::METHOD_POST, "/message-templates/{$messageTemplate->getUid()}/edit", [
            'message_template' => [
                '_token' => $this->generateCsrfToken($client, 'message_template'),
                'name' => $newName,
                'type' => $newType,
                'content' => $newContent,
            ],
        ]);

        $this->assertResponseRedirects("/message-templates/{$messageTemplate->getUid()}/edit", 302);
        $messageTemplate->_refresh();
        $this->assertSame($newName, $messageTemplate->getName());
        $this->assertSame($newType, $messageTemplate->getType());
        $this->assertSame($newContent, $messageTemplate->getContent());
    }

    public function testPostEditFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $oldName = 'Old template name';
        $newName = '';
        $newType = 'solution';
        $newContent = '<p>Lorem ipsum</p>';
        $messageTemplate = Factory\MessageTemplateFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request(Request::METHOD_POST, "/message-templates/{$messageTemplate->getUid()}/edit", [
            'message_template' => [
                '_token' => $this->generateCsrfToken($client, 'message_template'),
                'name' => $newName,
                'type' => $newType,
                'content' => $newContent,
            ],
        ]);

        $this->assertSelectorTextContains('#message_template_name-error', 'Enter a name.');
        $this->clearEntityManager();
        $messageTemplate->_refresh();
        $this->assertSame($oldName, $messageTemplate->getName());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $oldName = 'Old template name';
        $newName = 'New template name';
        $newType = 'solution';
        $newContent = '<p>Lorem ipsum</p>';
        $messageTemplate = Factory\MessageTemplateFactory::createOne([
            'name' => $oldName,
        ]);

        $client->request(Request::METHOD_POST, "/message-templates/{$messageTemplate->getUid()}/edit", [
            'message_template' => [
                '_token' => 'not a token',
                'name' => $newName,
                'type' => $newType,
                'content' => $newContent,
            ],
        ]);

        $this->assertSelectorTextContains('#message_template-error', 'The security token is invalid');
        $this->clearEntityManager();
        $messageTemplate->_refresh();
        $this->assertSame($oldName, $messageTemplate->getName());
    }

    public function testPostDeleteRemovesTheMessageTemplateAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $messageTemplate = Factory\MessageTemplateFactory::createOne();

        $client->request(Request::METHOD_POST, "/message-templates/{$messageTemplate->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete message template'),
        ]);

        $this->assertResponseRedirects('/message-templates', 302);
        Factory\MessageTemplateFactory::assert()->notExists(['id' => $messageTemplate->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:templates']);
        $messageTemplate = Factory\MessageTemplateFactory::createOne();

        $client->request(Request::METHOD_POST, "/message-templates/{$messageTemplate->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertResponseRedirects("/message-templates/{$messageTemplate->getUid()}/edit", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        Factory\MessageTemplateFactory::assert()->exists(['id' => $messageTemplate->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $messageTemplate = Factory\MessageTemplateFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/message-templates/{$messageTemplate->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete message template'),
        ]);
    }
}
