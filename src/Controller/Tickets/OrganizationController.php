<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Form;
use App\Entity;
use App\Repository;
use App\TicketActivity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OrganizationController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/organization/edit', name: 'edit ticket organization')]
    public function edit(
        Entity\Ticket $ticket,
        Request $request,
        Repository\TicketRepository $ticketRepository,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:organization', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $oldOrganization = $ticket->getOrganization();

        $form = $this->createNamedForm('ticket_organization', Form\Ticket\OrganizationForm::class, $ticket);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticket = $form->getData();
            $ticketRepository->save($ticket, true);

            $newOrganization = $ticket->getOrganization();

            if ($oldOrganization->getId() !== $newOrganization->getId()) {
                $ticketEvent = new TicketActivity\TicketEvent($ticket);
                $eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::TRANSFERRED);
            }

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('tickets/organization/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }
}
