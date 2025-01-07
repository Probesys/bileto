<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
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
use Symfony\Component\Routing\Annotation\Route;

class MessagesController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/messages/new', name: 'create ticket message', methods: ['POST'])]
    public function create(
        Entity\Ticket $ticket,
        Request $request,
        Repository\MessageRepository $messageRepository,
        Repository\TicketRepository $ticketRepository,
        Service\TicketTimeAccounting $ticketTimeAccounting,
        Service\TicketTimeline $ticketTimeline,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets:messages', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $timeline = $ticketTimeline->build($ticket);

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

        $ticketRepository->save($ticket, true);
        $messageRepository->save($message, true);

        $minutesSpent = $form->has('timeSpent') ? $form->get('timeSpent')->getData() : 0;
        if ($minutesSpent > 0) {
            $ticketTimeAccounting->accountTime($minutesSpent, $ticket, $message);
        }

        $type = $form->has('type') ? $form->get('type')->getData() : 'normal';
        $solutionAction = $form->has('solutionAction') ? $form->get('solutionAction')->getData() : 'nothing';

        $messageEvent = new TicketActivity\MessageEvent($message);

        $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED);

        if ($type === 'solution') {
            $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED_SOLUTION);
        } elseif ($solutionAction === 'approve') {
            $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::APPROVED_SOLUTION);
        } elseif ($solutionAction === 'refuse') {
            $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::REFUSED_SOLUTION);
        } else {
            $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED_ANSWER);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
