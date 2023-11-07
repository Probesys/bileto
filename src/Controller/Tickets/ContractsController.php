<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\ContractRepository;
use App\Repository\TicketRepository;
use App\Service\Sorter\ContractSorter;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContractsController extends BaseController
{
    #[Route('/tickets/{uid}/contracts/edit', name: 'edit ticket contracts', methods: ['GET', 'HEAD'])]
    public function edit(
        Ticket $ticket,
        ContractRepository $contractRepository,
        ContractSorter $contractSorter,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:contracts', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $ongoingContracts = $contractRepository->findOngoingByOrganization($organization);
        $contractSorter->sort($ongoingContracts);
        $initialOngoingContract = $ticket->getOngoingContract();

        return $this->render('tickets/contracts/edit.html.twig', [
            'ticket' => $ticket,
            'ongoingContracts' => $ongoingContracts,
            'ongoingContractUid' => $initialOngoingContract ? $initialOngoingContract->getUid() : null,
        ]);
    }

    #[Route('/tickets/{uid}/contracts/edit', name: 'update ticket contracts', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        ContractRepository $contractRepository,
        TicketRepository $ticketRepository,
        ContractSorter $contractSorter,
        TranslatorInterface $translator,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:contracts', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $ongoingContractUid = $request->request->getString('ongoingContractUid');

        $csrfToken = $request->request->getString('_csrf_token');

        $ongoingContracts = $contractRepository->findOngoingByOrganization($organization);
        $contractSorter->sort($ongoingContracts);
        $initialOngoingContract = $ticket->getOngoingContract();

        if (!$this->isCsrfTokenValid('update ticket contracts', $csrfToken)) {
            return $this->renderBadRequest('tickets/contracts/edit.html.twig', [
                'ticket' => $ticket,
                'ongoingContracts' => $ongoingContracts,
                'ongoingContractUid' => $ongoingContractUid,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $newOngoingContract = null;
        foreach ($ongoingContracts as $contract) {
            if ($contract->getUid() === $ongoingContractUid) {
                $newOngoingContract = $contract;
            }
        }

        if ($initialOngoingContract) {
            $ticket->removeContract($initialOngoingContract);
        }

        if ($newOngoingContract) {
            $ticket->addContract($newOngoingContract);
        }

        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}