<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Security;
use App\TicketActivity\TicketEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActorsController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/actors/edit', name: 'edit ticket actors')]
    public function edit(
        Entity\Ticket $ticket,
        Request $request,
        Repository\TicketRepository $ticketRepository,
        Security\Authorizer $authorizer,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $initialAssignee = $ticket->getAssignee();

        $form = $this->createNamedForm('ticket_actors', Form\Ticket\ActorsForm::class, $ticket);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticket = $form->getData();
            $ticketRepository->save($ticket, true);

            $newAssignee = $ticket->getAssignee();

            if ($initialAssignee != $newAssignee) {
                $ticketEvent = new TicketEvent($ticket);
                $eventDispatcher->dispatch($ticketEvent, TicketEvent::ASSIGNED);
            }

            if (!$authorizer->isGranted('orga:see', $ticket)) {
                $this->addFlash('success', $translator->trans('tickets.actors.edit.no_access'));

                return $this->redirectToRoute('organization tickets', [
                    'uid' => $ticket->getOrganization()->getUid(),
                ]);
            }

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('tickets/actors/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }
}
