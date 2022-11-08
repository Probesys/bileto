<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Message;
use App\Entity\Ticket;
use App\Repository\MessageRepository;
use App\Utils\Time;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessagesController extends BaseController
{
    #[Route('/tickets/{uid}/messages/new', name: 'create ticket message', methods: ['POST'])]
    public function create(
        Ticket $ticket,
        Request $request,
        MessageRepository $messageRepository,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $messageContent */
        $messageContent = $request->request->get('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create ticket message', $csrfToken)) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'messages' => $ticket->getMessages(),
                'organization' => $ticket->getOrganization(),
                'message' => $messageContent,
                'error' => $this->csrfError(),
            ]);
        }

        $message = new Message();
        $message->setContent($messageContent);
        $message->setCreatedAt(Time::now());
        $message->setCreatedBy($user);
        $message->setTicket($ticket);
        $message->setIsPrivate(false);
        $message->setIsSolution(false);
        $message->setVia('webapp');

        $errors = $validator->validate($message);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'messages' => $ticket->getMessages(),
                'organization' => $ticket->getOrganization(),
                'message' => $messageContent,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $messageRepository->save($message, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
