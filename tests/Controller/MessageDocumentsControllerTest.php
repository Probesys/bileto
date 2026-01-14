<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Repository\MessageDocumentRepository;
use App\Service\MessageDocumentStorage;
use App\Tests;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\MessageDocumentFactory;
use App\Tests\Factory\UserFactory;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessageDocumentsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\MessageDocumentStorageHelper;
    use Tests\SessionHelper;

    public function testPostCreateSavesTheFile(): void
    {
        $client = static::createClient();
        /** @var RouterInterface */
        $router = static::getContainer()->get('router');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $filepath = sys_get_temp_dir() . '/document.txt';
        $content = 'Hello World!';
        $hash = hash('sha256', $content);
        file_put_contents($filepath, $content);
        $document = new UploadedFile($filepath, 'My document');

        $this->assertSame(0, MessageDocumentFactory::count());

        $client->request(Request::METHOD_POST, '/messages/documents/new', [
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
        $this->assertSame($expectedUrlShow, $responseData['urlShow']);
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $filepath = sys_get_temp_dir() . '/document.txt';
        $content = 'Hello World!';
        $hash = hash('sha256', $content);
        file_put_contents($filepath, $content);
        $document = new UploadedFile($filepath, 'My document');

        $this->assertSame(0, MessageDocumentFactory::count());

        $client->request(Request::METHOD_POST, '/messages/documents/new', [
            '_csrf_token' => 'not the token',
        ], [
            'document' => $document,
        ]);

        $this->assertSame(0, MessageDocumentFactory::count());
        $response = $client->getResponse();
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
        $this->assertStringContainsString('The security token is invalid', $responseData['error']);
    }

    public function testPostCreateFailsIfMimetypeIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $filepath = sys_get_temp_dir() . '/document.mp3';
        touch($filepath);
        $document = new UploadedFile($filepath, 'My audio file');

        $this->assertSame(0, MessageDocumentFactory::count());

        $client->request(Request::METHOD_POST, '/messages/documents/new', [
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);

        $client->request(Request::METHOD_POST, '/messages/documents/new', [
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
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $content = 'Hello World!';
        $hash = hash('sha256', $content);
        file_put_contents($filepath, $content);
        $document = new UploadedFile($filepath, 'My document');

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, '/messages/documents/new', [
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
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $this->assertNull($messageDocument->getMessage());

        $client->request(Request::METHOD_GET, "/messages/documents/{$messageDocument->getUid()}.txt");

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
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);
        $client->loginUser($otherUser->_real());

        $this->assertNull($messageDocument->getMessage());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/messages/documents/{$messageDocument->getUid()}.txt");
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
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');

        $message = MessageFactory::createOne();
        $messageDocument->setMessage($message->_real());
        $messageDocumentRepository->save($messageDocument, true);

        $client->loginUser($otherUser->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/messages/documents/{$messageDocument->getUid()}.txt");
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
        $this->grantOrga($otherUser->_real(), ['orga:see:tickets:all']);
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');

        $message = MessageFactory::createOne([
            'isConfidential' => true,
        ]);
        $messageDocument->setMessage($message->_real());
        $messageDocumentRepository->save($messageDocument, true);

        $client->loginUser($otherUser->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/messages/documents/{$messageDocument->getUid()}.txt");
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
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/messages/documents/{$messageDocument->getUid()}.pdf");
    }

    public function testPostDeleteRemovesTheDocument(): void
    {
        $client = static::createClient();
        /** @var RouterInterface */
        $router = static::getContainer()->get('router');
        /** @var MessageDocumentStorage */
        $messageDocumentStorage = static::getContainer()->get(MessageDocumentStorage::class);
        /** @var MessageDocumentRepository */
        $messageDocumentRepository = static::getContainer()->get(MessageDocumentRepository::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $this->assertSame(1, MessageDocumentFactory::count());
        $this->assertTrue($messageDocumentStorage->exists($messageDocument));

        $client->request(Request::METHOD_POST, "/messages/documents/{$messageDocument->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete message document'),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, MessageDocumentFactory::count());
        $this->assertFalse($messageDocumentStorage->exists($messageDocument));

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
        $this->assertSame($expectedUrlShow, $responseData['urlShow']);
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
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);
        $otherUser = UserFactory::createOne();
        $client->loginUser($otherUser->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/messages/documents/{$messageDocument->getUid()}/deletion", [
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
        $client->loginUser($user->_real());
        $filepath = sys_get_temp_dir() . '/document.txt';
        $expectedContent = 'Hello World!';
        $hash = hash('sha256', $expectedContent);
        file_put_contents($filepath, $expectedContent);
        $document = new File($filepath);
        $messageDocument = $messageDocumentStorage->store($document, 'My document');
        $messageDocumentRepository->save($messageDocument, true);

        $client->request(Request::METHOD_POST, "/messages/documents/{$messageDocument->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertSame(1, MessageDocumentFactory::count());
        $this->assertTrue($messageDocumentStorage->exists($messageDocument));

        $response = $client->getResponse();
        /** @var string */
        $content = $response->getContent();
        $responseData = json_decode($content, true);
        $this->assertStringContainsString('The security token is invalid', $responseData['error']);
    }

    public function testGetIndexListsTheFile(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $message = MessageFactory::createOne();
        $messageDocument1 = MessageDocumentFactory::createOne([
            'createdAt' => Time::ago(2, 'hour'),
            'createdBy' => $user->_real(),
            'name' => 'foo.txt',
            'message' => $message,
        ]);
        $messageDocument2 = MessageDocumentFactory::createOne([
            'createdAt' => Time::ago(1, 'hour'),
            'createdBy' => $user->_real(),
            'name' => 'bar.txt',
            'message' => null,
        ]);

        $client->request(Request::METHOD_GET, '/messages/documents');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '[data-target="document-item"]:nth-child(1) [data-target="document-name"]',
            'foo.txt'
        );
        $this->assertSelectorTextContains(
            '[data-target="document-item"]:nth-child(2) [data-target="document-name"]',
            'bar.txt'
        );
    }

    public function testGetIndexCanFilterUnattachedDocuments(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $message = MessageFactory::createOne();
        $messageDocument1 = MessageDocumentFactory::createOne([
            'createdAt' => Time::ago(2, 'hour'),
            'createdBy' => $user->_real(),
            'name' => 'foo.txt',
            'message' => $message,
        ]);
        $messageDocument2 = MessageDocumentFactory::createOne([
            'createdAt' => Time::ago(1, 'hour'),
            'createdBy' => $user->_real(),
            'name' => 'bar.txt',
            'message' => null,
        ]);

        $client->request(Request::METHOD_GET, '/messages/documents?filter=unattached');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '[data-target="document-item"]:nth-child(1) [data-target="document-name"]',
            'bar.txt'
        );
    }
}
