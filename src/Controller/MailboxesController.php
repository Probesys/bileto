<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Mailbox;
use App\Message\FetchMailboxes;
use App\Message\CreateTicketsFromMailboxEmails;
use App\Repository\MailboxRepository;
use App\Repository\MailboxEmailRepository;
use App\Security\Encryptor;
use App\Service\MailboxSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webklex\PHPIMAP;

class MailboxesController extends BaseController
{
    #[Route('/mailboxes', name: 'mailboxes', methods: ['GET', 'HEAD'])]
    public function index(
        MailboxRepository $mailboxRepository,
        MailboxEmailRepository $mailboxEmailRepository,
        MailboxSorter $mailboxSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        $mailboxes = $mailboxRepository->findAll();
        $mailboxSorter->sort($mailboxes);

        $errorMailboxEmails = $mailboxEmailRepository->findInError();

        return $this->render('mailboxes/index.html.twig', [
            'mailboxes' => $mailboxes,
            'errorMailboxEmails' => $errorMailboxEmails,
        ]);
    }

    #[Route('/mailboxes/new', name: 'new mailbox', methods: ['GET', 'HEAD'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        return $this->render('mailboxes/new.html.twig', [
            'name' => '',
            'host' => '',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => '',
            'password' => '',
            'folder' => 'INBOX',
        ]);
    }

    #[Route('/mailboxes/new', name: 'create mailbox', methods: ['POST'])]
    public function create(
        Request $request,
        MailboxRepository $mailboxRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        Encryptor $encryptor,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $host */
        $host = $request->request->get('host', '');

        /** @var integer $port */
        $port = $request->request->getInt('port', 993);

        /** @var string $encryption */
        $encryption = $request->request->get('encryption', '');

        /** @var string $username */
        $username = $request->request->get('username', '');

        /** @var string $password */
        $password = $request->request->get('password', '');

        /** @var string $folder */
        $folder = $request->request->get('folder', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create mailbox', $csrfToken)) {
            return $this->renderBadRequest('mailboxes/new.html.twig', [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $password,
                'folder' => $folder,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $mailbox = new Mailbox();
        $mailbox->setName($name);
        $mailbox->setHost($host);
        $mailbox->setProtocol('imap');
        $mailbox->setPort($port);
        $mailbox->setEncryption($encryption);
        $mailbox->setUsername($username);
        $mailbox->setAuthentication('normal');
        $mailbox->setFolder($folder);

        if ($password) {
            $encryptedPassword = $encryptor->encrypt($password);
            $mailbox->setPassword($encryptedPassword);
        }

        $errors = $validator->validate($mailbox);
        if (count($errors) > 0) {
            return $this->renderBadRequest('mailboxes/new.html.twig', [
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $password,
                'folder' => $folder,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $mailboxRepository->save($mailbox, true);

        return $this->redirectToRoute('mailboxes');
    }

    #[Route('/mailboxes/{uid}/edit', name: 'edit mailbox', methods: ['GET', 'HEAD'])]
    public function edit(Mailbox $mailbox): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        return $this->render('mailboxes/edit.html.twig', [
            'mailbox' => $mailbox,
            'name' => $mailbox->getName(),
            'host' => $mailbox->getHost(),
            'port' => $mailbox->getPort(),
            'encryption' => $mailbox->getEncryption(),
            'username' => $mailbox->getUsername(),
            'folder' => $mailbox->getFolder(),
        ]);
    }

    #[Route('/mailboxes/{uid}/edit', name: 'update mailbox', methods: ['POST'])]
    public function update(
        Mailbox $mailbox,
        Request $request,
        MailboxRepository $mailboxRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        Encryptor $encryptor,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $host */
        $host = $request->request->get('host', '');

        /** @var integer $port */
        $port = $request->request->getInt('port', 993);

        /** @var string $encryption */
        $encryption = $request->request->get('encryption', '');

        /** @var string $username */
        $username = $request->request->get('username', '');

        /** @var string $password */
        $password = $request->request->get('password', '');

        /** @var string $folder */
        $folder = $request->request->get('folder', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update mailbox', $csrfToken)) {
            return $this->renderBadRequest('mailboxes/edit.html.twig', [
                'mailbox' => $mailbox,
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'folder' => $folder,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $mailbox->setName($name);
        $mailbox->setHost($host);
        $mailbox->setProtocol('imap');
        $mailbox->setPort($port);
        $mailbox->setEncryption($encryption);
        $mailbox->setUsername($username);
        $mailbox->setAuthentication('normal');
        $mailbox->setFolder($folder);

        if ($password) {
            $encryptedPassword = $encryptor->encrypt($password);
            $mailbox->setPassword($encryptedPassword);
        }

        $errors = $validator->validate($mailbox);
        if (count($errors) > 0) {
            return $this->renderBadRequest('mailboxes/edit.html.twig', [
                'mailbox' => $mailbox,
                'name' => $name,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'folder' => $folder,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $mailboxRepository->save($mailbox, true);

        return $this->redirectToRoute('edit mailbox', [
            'uid' => $mailbox->getUid(),
        ]);
    }

    #[Route('/mailboxes/{uid}/test', name: 'test mailbox', methods: ['POST'])]
    public function test(
        Request $request,
        Mailbox $mailbox,
        MailboxRepository $mailboxRepository,
        TranslatorInterface $translator,
        Encryptor $encryptor,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('test mailbox', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('mailboxes');
        }

        $clientManager = new PHPIMAP\ClientManager();
        $client = $clientManager->make([
            'host' => $mailbox->getHost(),
            'protocol' => $mailbox->getProtocol(),
            'port' => $mailbox->getPort(),
            'encryption' => $mailbox->getEncryption(),
            'validate_cert' => true,
            'username' => $mailbox->getUsername(),
            'password' => $encryptor->decrypt($mailbox->getPassword()),
        ]);

        try {
            $client->connect();
            $client->disconnect();

            $mailbox->setLastError('');
            $mailboxRepository->save($mailbox, true);

            $this->addFlash('success', new TranslatableMessage('mailboxes.test.success'));
        } catch (\Exception $e) {
            $error = $e->getMessage();

            $mailbox->setLastError($error);
            $mailboxRepository->save($mailbox, true);
        }

        return $this->redirectToRoute('mailboxes');
    }

    #[Route('/mailboxes/collect', name: 'collect mailboxes', methods: ['POST'])]
    public function collect(
        Request $request,
        TranslatorInterface $translator,
        MessageBusInterface $bus,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('collect mailboxes', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('mailboxes');
        }

        $bus->dispatch(new FetchMailboxes(), [
            new TransportNamesStamp('sync'),
        ]);
        $bus->dispatch(new CreateTicketsFromMailboxEmails(), [
            new TransportNamesStamp('sync'),
        ]);

        return $this->redirectToRoute('mailboxes');
    }

    #[Route('/mailboxes/{uid}/deletion', name: 'delete mailbox', methods: ['POST'])]
    public function delete(
        Mailbox $mailbox,
        Request $request,
        MailboxRepository $mailboxRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete mailbox', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('mailboxes');
        }

        $mailboxRepository->remove($mailbox, true);

        return $this->redirectToRoute('mailboxes');
    }
}
