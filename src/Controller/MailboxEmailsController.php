<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Repository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxEmailsController extends BaseController
{
    public function __construct(
        private readonly Repository\MailboxEmailRepository $mailboxEmailRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/mailbox-emails/{uid:mailboxEmail}/deletion', name: 'delete mailbox email', methods: ['POST'])]
    public function delete(Entity\MailboxEmail $mailboxEmail, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete mailbox email', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('mailboxes');
        }

        $this->mailboxEmailRepository->remove($mailboxEmail, true);

        return $this->redirectToRoute('mailboxes');
    }
}
