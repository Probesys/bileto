<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Repository\MailboxRepository;
use App\Service\MailboxSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
}
