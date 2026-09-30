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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContractsController extends BaseController
{
    public function __construct(
        private readonly Repository\TicketRepository $ticketRepository,
        private readonly Repository\TimeSpentRepository $timeSpentRepository,
        private readonly Repository\ContractRepository $contractRepository,
        private readonly Service\ContractTimeAccounting $contractTimeAccounting,
    ) {
    }

    #[Route('/tickets/{uid:ticket}/contracts/edit', name: 'edit ticket contracts')]
    public function edit(Entity\Ticket $ticket, Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:update:tickets:contracts', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $preferredContract = $ticket->getOngoingContract()
            ?? $this->contractRepository->findPreferredForTicketContext($ticket);
        $form = $this->createNamedForm(
            'ticket_ongoing_contract',
            Form\Ticket\OngoingContractForm::class,
            $ticket,
            [
                'preferred_contract' => $preferredContract,
            ],
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ongoingContract = $form->get('ongoingContract')->getData();
            $this->ticketRepository->save($ticket, true);

            $includeUnaccountedTime = $form->get('includeUnaccountedTime')->getData();
            if ($includeUnaccountedTime && $ongoingContract) {
                $timeSpents = $ticket->getUnaccountedTimeSpents()->getValues();
                $this->contractTimeAccounting->accountTimeSpents($ongoingContract, $timeSpents);
                $this->timeSpentRepository->save($timeSpents, true);
            }

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('tickets/contracts/edit.html.twig', [
            'ticket' => $ticket,
            'preferredContract' => $preferredContract,
            'form' => $form,
        ]);
    }

    #[Route('/tickets/{uid:ticket}/contracts/search', name: 'search ticket ongoing contracts', methods: ['GET'])]
    public function search(Entity\Ticket $ticket, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('orga:update:tickets:contracts', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);
        $search = trim($request->query->getString('q'));
        $organization = $ticket->getOrganization();
        if (!$organization) {
            return $this->json(['items' => []]);
        }

        $contracts = $this->contractRepository->searchOngoingByOrganization(
            $organization,
            $search,
        );

        return $this->json([
            'items' => array_map(static fn (Entity\Contract $contract): array => [
                'id' => $contract->getId(),
                'name' => $contract->getName(),
            ], $contracts),
        ]);
    }
}
