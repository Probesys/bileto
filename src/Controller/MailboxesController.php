<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Message;
use App\Repository;
use App\Security;
use App\Service;
use App\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webklex\PHPIMAP;

class MailboxesController extends BaseController
{
    #[Route('/mailboxes', name: 'mailboxes', methods: ['GET', 'HEAD'])]
    public function index(
        Repository\MailboxRepository $mailboxRepository,
        Repository\MailboxEmailRepository $mailboxEmailRepository,
        Service\Sorter\MailboxSorter $mailboxSorter,
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

    #[Route('/mailboxes/new', name: 'new mailbox')]
    public function new(
        Request $request,
        Repository\MailboxRepository $mailboxRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        $mailbox = new Entity\Mailbox();
        $form = $this->createNamedForm('mailbox', Form\MailboxForm::class, $mailbox);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mailbox = $form->getData();
            $mailboxRepository->save($mailbox, true);

            return $this->redirectToRoute('mailboxes');
        }

        return $this->render('mailboxes/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/mailboxes/{uid:mailbox}/edit', name: 'edit mailbox')]
    public function edit(
        Entity\Mailbox $mailbox,
        Request $request,
        Repository\MailboxRepository $mailboxRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        $form = $this->createNamedForm('mailbox', Form\MailboxForm::class, $mailbox);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mailbox = $form->getData();
            $mailbox->resetLastError();
            $mailboxRepository->save($mailbox, true);

            $this->addFlash('success', new TranslatableMessage('notifications.saved'));

            return $this->redirectToRoute('edit mailbox', [
                'uid' => $mailbox->getUid(),
            ]);
        }

        return $this->render('mailboxes/edit.html.twig', [
            'mailbox' => $mailbox,
            'form' => $form,
        ]);
    }

    #[Route('/mailboxes/{uid:mailbox}/test', name: 'test mailbox', methods: ['POST'])]
    public function test(
        Request $request,
        Entity\Mailbox $mailbox,
        Repository\MailboxRepository $mailboxRepository,
        TranslatorInterface $translator,
        Security\Encryptor $encryptor,
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

        return $this->redirectToRoute('edit mailbox', [
            'uid' => $mailbox->getUid(),
        ]);
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

        $bus->dispatch(new Message\FetchMailboxes(), [
            new TransportNamesStamp('sync'),
        ]);
        $bus->dispatch(new Message\CreateTicketsFromMailboxEmails(), [
            new TransportNamesStamp('sync'),
        ]);

        return $this->redirectToRoute('mailboxes');
    }

    #[Route('/mailboxes/{uid:mailbox}/deletion', name: 'delete mailbox', methods: ['POST'])]
    public function delete(
        Entity\Mailbox $mailbox,
        Request $request,
        Repository\MailboxRepository $mailboxRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete mailbox', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit mailbox', [
                'uid' => $mailbox->getUid(),
            ]);
        }

        $mailboxRepository->remove($mailbox, true);

        return $this->redirectToRoute('mailboxes');
    }
}
