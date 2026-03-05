<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Message;
use App\Repository;
use App\Service;
use App\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxesController extends BaseController
{
    public function __construct(
        private readonly Repository\MailboxRepository $mailboxRepository,
        private readonly Repository\MailboxEmailRepository $mailboxEmailRepository,
        private readonly Service\Sorter\MailboxSorter $mailboxSorter,
        private readonly Service\MailboxService $mailboxService,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $bus
    ) {
    }

    #[Route('/mailboxes', name: 'mailboxes', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');
        $mailboxes = $this->mailboxRepository->findAll();
        $this->mailboxSorter->sort($mailboxes);
        $errorMailboxEmails = $this->mailboxEmailRepository->findInError();
        return $this->render('mailboxes/index.html.twig', [
            'mailboxes' => $mailboxes,
            'errorMailboxEmails' => $errorMailboxEmails,
        ]);
    }

    #[Route('/mailboxes/new', name: 'new mailbox')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        $mailbox = new Entity\Mailbox();
        $form = $this->createNamedForm('mailbox', Form\MailboxForm::class, $mailbox);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mailbox = $form->getData();
            $this->mailboxRepository->save($mailbox, true);

            return $this->redirectToRoute('mailboxes');
        }

        return $this->render('mailboxes/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/mailboxes/{uid:mailbox}/edit', name: 'edit mailbox')]
    public function edit(Entity\Mailbox $mailbox, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        $form = $this->createNamedForm('mailbox', Form\MailboxForm::class, $mailbox);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mailbox = $form->getData();
            $mailbox->resetLastError();
            $this->mailboxRepository->save($mailbox, true);

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
    public function test(Request $request, Entity\Mailbox $mailbox): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('test mailbox', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('mailboxes');
        }

        $client = $this->mailboxService->getClient($mailbox);

        try {
            $client->connect();
            $client->disconnect();

            $mailbox->setLastError('');
            $this->mailboxRepository->save($mailbox, true);

            $this->addFlash('success', new TranslatableMessage('mailboxes.test.success'));
        } catch (\Exception $e) {
            $error = $e->getMessage();

            $mailbox->setLastError($error);
            $this->mailboxRepository->save($mailbox, true);
        }

        return $this->redirectToRoute('edit mailbox', [
            'uid' => $mailbox->getUid(),
        ]);
    }

    #[Route('/mailboxes/collect', name: 'collect mailboxes', methods: ['POST'])]
    public function collect(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('collect mailboxes', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('mailboxes');
        }

        $this->bus->dispatch(new Message\FetchMailboxes(), [
            new TransportNamesStamp('sync'),
        ]);
        $this->bus->dispatch(new Message\CreateTicketsFromMailboxEmails(), [
            new TransportNamesStamp('sync'),
        ]);

        return $this->redirectToRoute('mailboxes');
    }

    #[Route('/mailboxes/{uid:mailbox}/deletion', name: 'delete mailbox', methods: ['POST'])]
    public function delete(Entity\Mailbox $mailbox, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete mailbox', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit mailbox', [
                'uid' => $mailbox->getUid(),
            ]);
        }

        $this->mailboxRepository->remove($mailbox, true);

        return $this->redirectToRoute('mailboxes');
    }
}
