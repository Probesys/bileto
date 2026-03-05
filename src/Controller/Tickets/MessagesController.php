<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use App\TicketActivity;
use App\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class MessagesController extends BaseController
{
    public function __construct(
        private readonly Repository\MessageRepository $messageRepository,
        private readonly Repository\TicketRepository $ticketRepository,
        private readonly Service\TicketTimeAccounting $ticketTimeAccounting,
        private readonly Service\TicketTimeline $ticketTimeline,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/tickets/{uid:ticket}/messages/new', name: 'create ticket message', methods: ['POST'])]
    public function create(Entity\Ticket $ticket, Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:see', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $timeline = $this->ticketTimeline->build($ticket);

        $message = new Entity\Message();
        $message->setTicket($ticket);

        $form = $this->createNamedForm('answer', Form\AnswerForm::class, $message);

        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $timeline,
                'organization' => $ticket->getOrganization(),
                'today' => Utils\Time::relative('today'),
                'form' => $form,
            ]);
        }

        /** @var \App\Entity\User */
        $user = $this->getUser();

        $ticket->setUpdatedAt(Utils\Time::now());
        $ticket->setUpdatedBy($user);

        $this->ticketRepository->save($ticket, true);
        $this->messageRepository->save($message, true);

        $minutesSpent = $form->has('timeSpent') ? $form->get('timeSpent')->getData() : 0;
        if ($minutesSpent > 0) {
            $this->ticketTimeAccounting->accountTime($minutesSpent, $ticket, $message);
        }

        $type = $form->has('type') ? $form->get('type')->getData() : 'normal';
        $solutionAction = $form->has('solutionAction') ? $form->get('solutionAction')->getData() : 'nothing';

        $messageEvent = new TicketActivity\MessageEvent($message);

        $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED);

        if ($type === 'solution') {
            $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED_SOLUTION);
        } elseif ($solutionAction === 'approve') {
            $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::APPROVED_SOLUTION);
        } elseif ($solutionAction === 'refuse') {
            $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::REFUSED_SOLUTION);
        } else {
            $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED_ANSWER);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
