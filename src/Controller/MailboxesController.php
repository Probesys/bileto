<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Mailbox;
use App\Repository\MailboxRepository;
use App\Security\Encryptor;
use App\Service\MailboxSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxesController extends BaseController
{
    #[Route('/mailboxes', name: 'mailboxes', methods: ['GET', 'HEAD'])]
    public function index(
        MailboxRepository $mailboxRepository,
        MailboxSorter $mailboxSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        $mailboxes = $mailboxRepository->findAll();
        $mailboxSorter->sort($mailboxes);

        return $this->render('mailboxes/index.html.twig', [
            'mailboxes' => $mailboxes,
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
}
