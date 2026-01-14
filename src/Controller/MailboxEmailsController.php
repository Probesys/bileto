<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\MailboxEmail;
use App\Repository\MailboxEmailRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailboxEmailsController extends BaseController
{
    #[Route('/mailbox-emails/{uid:mailboxEmail}/deletion', name: 'delete mailbox email', methods: ['POST'])]
    public function delete(
        MailboxEmail $mailboxEmail,
        Request $request,
        MailboxEmailRepository $mailboxEmailRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:mailboxes');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete mailbox email', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('mailboxes');
        }

        $mailboxEmailRepository->remove($mailboxEmail, true);

        return $this->redirectToRoute('mailboxes');
    }
}
