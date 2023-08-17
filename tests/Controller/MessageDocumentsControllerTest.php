<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MessageDocumentFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessageDocumentsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testPostCreateSavesTheFile(): void
    {
        $client = static::createClient();
        $router = static::getContainer()->get('router');
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $filepath = sys_get_temp_dir() . '/document.txt';
        $content = 'Hello World!';
        $hash = hash('sha256', $content);
        file_put_contents($filepath, $content);
        $document = new UploadedFile($filepath, 'My document');

        $this->assertSame(0, MessageDocumentFactory::count());

        $client->request('POST', '/messages/documents/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create message document'),
        ], [
            'document' => $document,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, MessageDocumentFactory::count());
        $messageDocument = MessageDocumentFactory::first();
        $this->assertSame('My document', $messageDocument->getName());
        $this->assertSame($hash . '.txt', $messageDocument->getFilename());
        $this->assertSame('text/plain', $messageDocument->getMimetype());
        $this->assertSame('sha256:' . $hash, $messageDocument->getHash());
        $this->assertNull($messageDocument->getMessage());

        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $expectedUrlShow = $router->generate(
            'message document',
            [
                'uid' => $messageDocument->getUid(),
                'extension' => $messageDocument->getExtension(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $this->assertSame($messageDocument->getUid(), $responseData['uid']);
        $this->assertSame($messageDocument->getName(), $responseData['name']);
        $this->assertSame($expectedUrlShow, $responseData['urlShow']);
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $filepath = sys_get_temp_dir() . '/document.txt';
        $content = 'Hello World!';
        $hash = hash('sha256', $content);
        file_put_contents($filepath, $content);
        $document = new UploadedFile($filepath, 'My document');

        $this->assertSame(0, MessageDocumentFactory::count());

        $client->request('POST', '/messages/documents/new', [
            '_csrf_token' => 'not the token',
        ], [
            'document' => $document,
        ]);

        $this->assertSame(0, MessageDocumentFactory::count());
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame('The security token is invalid, please try again.', $responseData['error']);
    }

    public function testPostCreateFailsIfMimetypeIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $filepath = sys_get_temp_dir() . '/document.mp3';
        touch($filepath);
        $document = new UploadedFile($filepath, 'My audio file');

        $this->assertSame(0, MessageDocumentFactory::count());

        $client->request('POST', '/messages/documents/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create message document'),
        ], [
            'document' => $document,
        ]);

        $this->assertSame(0, MessageDocumentFactory::count());
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame('You cannot upload this type of file, choose another file.', $responseData['error']);
    }

    public function testPostCreateFailsIfDocumentIsMissing(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);

        $client->request('POST', '/messages/documents/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create message document'),
        ]);

        $this->assertSame(0, MessageDocumentFactory::count());
        $response = $client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame('Select a file.', $responseData['error']);
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $content = 'Hello World!';
        $hash = hash('sha256', $content);
        file_put_contents($filepath, $content);
        $document = new UploadedFile($filepath, 'My document');

        $client->catchExceptions(false);
        $client->request('POST', '/messages/documents/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create message document'),
        ], [
            'document' => $document,
        ]);
    }
}
