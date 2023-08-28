<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Repository\MessageDocumentRepository;
use App\Service\MessageDocumentStorage;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\MessageDocumentFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
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
        /** @var RouterInterface */
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
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
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
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
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
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
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
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
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

    public function testGetShowServesTheFile(): void
    {
        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $this->assertNull($messageDocument->getMessage());

        $client->request('GET', "/messages/documents/{$messageDocument->getUid()}.txt");

        $response = $client->getResponse();
        $content = $response->getContent();
        $this->assertSame($expectedContent, $content);
        $this->assertSame('attachment; filename="My%20document"', $response->headers->get('Content-Disposition'));
        $this->assertSame('12', $response->headers->get('Content-Length'));
        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testGetShowFailsIfCurrentUserIsNotAuthor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);
        $client->loginUser($otherUser->object());

        $this->assertNull($messageDocument->getMessage());

        $client->catchExceptions(false);
        $client->request('GET', "/messages/documents/{$messageDocument->getUid()}.txt");
    }

    public function testGetShowFailsIfMessageIsSetAndCurrentUserIsNotActorOfTheAssociatedTicket(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');

        $message = MessageFactory::createOne();
        $messageDocument->setMessage($message->object());
        $messageDocumentRepository->save($messageDocument, true);

        $client->loginUser($otherUser->object());

        $client->catchExceptions(false);
        $client->request('GET', "/messages/documents/{$messageDocument->getUid()}.txt");
    }

    public function testGetShowFailsIfMessageIsSetAndCurrentUserCannotReadConfidentialMessage(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $this->grantOrga($otherUser->object(), ['orga:see:tickets:all']);
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');

        $message = MessageFactory::createOne([
            'isConfidential' => true,
        ]);
        $messageDocument->setMessage($message->object());
        $messageDocumentRepository->save($messageDocument, true);

        $client->loginUser($otherUser->object());

        $client->catchExceptions(false);
        $client->request('GET', "/messages/documents/{$messageDocument->getUid()}.txt");
    }

    public function testGetShowFailsIfExtensionDoesNotMatch(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $client->catchExceptions(false);
        $client->request('GET', "/messages/documents/{$messageDocument->getUid()}.pdf");
    }

    public function testPostDeleteRemovesTheDocument(): void
    {
        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $this->assertSame(1, MessageDocumentFactory::count());
        $this->assertTrue($messageDocumentStorage->exists($messageDocument));

        $client->request('POST', "/messages/documents/{$messageDocument->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete message document'),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, MessageDocumentFactory::count());
        $this->assertFalse($messageDocumentStorage->exists($messageDocument));

        $response = $client->getResponse();
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
        $this->assertSame($messageDocument->getUid(), $responseData['uid']);
    }

    public function testPostDeleteFailsIfAccessIsDenied(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);
        $otherUser = UserFactory::createOne();
        $client->loginUser($otherUser->object());

        $client->catchExceptions(false);
        $client->request('POST', "/messages/documents/{$messageDocument->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete message document'),
        ]);
    }

    public function testPostDeleteFailsIfCsrfIsInvalid(): void
    {
        $client = static::createClient();
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $client->request('POST', "/messages/documents/{$messageDocument->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertSame(1, MessageDocumentFactory::count());
        $this->assertTrue($messageDocumentStorage->exists($messageDocument));

        $response = $client->getResponse();
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
        $this->assertSame('The security token is invalid, please try again.', $responseData['error']);
    }
}
