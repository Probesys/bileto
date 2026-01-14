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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ContractsController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/contracts/edit', name: 'edit ticket contracts')]
    public function edit(
        Entity\Ticket $ticket,
        Request $request,
        Repository\TicketRepository $ticketRepository,
        Repository\TimeSpentRepository $timeSpentRepository,
        Service\ContractTimeAccounting $contractTimeAccounting,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:contracts', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('ticket_ongoing_contract', Form\Ticket\OngoingContractForm::class, $ticket);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticket = $form->getData();
            $ticketRepository->save($ticket, true);

            $ongoingContract = $form->get('ongoingContract')->getData();
            $includeUnaccountedTime = $form->get('includeUnaccountedTime')->getData();

            if ($includeUnaccountedTime && $ongoingContract) {
                $timeSpents = $ticket->getUnaccountedTimeSpents()->getValues();
                $contractTimeAccounting->accountTimeSpents($ongoingContract, $timeSpents);
                $timeSpentRepository->save($timeSpents, true);
            }

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('tickets/contracts/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }
}
