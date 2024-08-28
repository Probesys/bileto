<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\EntityEvent;
use App\Entity\Ticket;
use App\Repository\ContractRepository;
use App\Repository\TicketRepository;
use App\Repository\TimeSpentRepository;
use App\Service\ContractTimeAccounting;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContractsController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/contracts/edit', name: 'edit ticket contracts', methods: ['GET', 'HEAD'])]
    public function edit(
        Ticket $ticket,
        ContractRepository $contractRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:contracts', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $organization = $ticket->getOrganization();
        $ongoingContracts = $contractRepository->findOngoingByOrganization($organization);
        $initialOngoingContract = $ticket->getOngoingContract();

        return $this->render('tickets/contracts/edit.html.twig', [
            'ticket' => $ticket,
            'ongoingContracts' => $ongoingContracts,
            'ongoingContractUid' => $initialOngoingContract ? $initialOngoingContract->getUid() : null,
        ]);
    }

    #[Route('/tickets/{uid:ticket}/contracts/edit', name: 'update ticket contracts', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        ContractRepository $contractRepository,
        TicketRepository $ticketRepository,
        TimeSpentRepository $timeSpentRepository,
        ContractTimeAccounting $contractTimeAccounting,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:contracts', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $organization = $ticket->getOrganization();

        $ongoingContractUid = $request->request->getString('ongoingContractUid');

        $includeUnaccountedTime = $request->request->getBoolean('includeUnaccountedTime');

        $csrfToken = $request->request->getString('_csrf_token');

        $ongoingContracts = $contractRepository->findOngoingByOrganization($organization);
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

        if ($includeUnaccountedTime && $newOngoingContract) {
            $timeSpents = $ticket->getUnaccountedTimeSpents()->getValues();
            $contractTimeAccounting->accountTimeSpents($newOngoingContract, $timeSpents);
            $timeSpentRepository->save($timeSpents, true);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
