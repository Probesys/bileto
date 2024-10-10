<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Entity\EntityEvent;
use App\Entity\Organization;
use App\Form;
use App\Repository\ContractRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Repository\TimeSpentRepository;
use App\Security\Authorizer;
use App\Service\ContractTimeAccounting;
use App\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ContractsController extends BaseController
{
    #[Route('/organizations/{uid:organization}/contracts', name: 'organization contracts', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        Organization $organization,
        ContractRepository $contractRepository,
        OrganizationRepository $organizationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $page = $request->query->getInt('page', 1);

        $contractsQuery = $contractRepository->findByOrganizationQuery($organization);

        $contractsPagination = Utils\Pagination::paginate($contractsQuery, [
            'page' => $page,
            'maxResults' => 25,
        ]);

        return $this->render('organizations/contracts/index.html.twig', [
            'organization' => $organization,
            'contractsPagination' => $contractsPagination,
        ]);
    }

    #[Route('/organizations/{uid:organization}/contracts/new', name: 'new organization contract')]
    public function new(
        Organization $organization,
        Request $request,
        ContractRepository $contractRepository,
        TicketRepository $ticketRepository,
        TimeSpentRepository $timeSpentRepository,
        ContractTimeAccounting $contractTimeAccounting,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $fromContractUid = $request->query->getString('from');

        $contract = null;
        if ($fromContractUid) {
            $contract = $contractRepository->findOneBy([
                'uid' => $fromContractUid,
            ]);
        }

        if ($contract) {
            $contract = $contract->getRenewed();
        } else {
            $contract = new Contract();
        }

        $form = $this->createNamedForm('contract', Form\ContractForm::class, $contract, [
            'allow_associate' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract = $form->getData();
            $contract->setOrganization($organization);
            $contract->initDefaultAlerts();
            $contractRepository->save($contract, true);

            $contractTickets = $ticketRepository->findAssociableTickets($contract);

            if ($form->get('associateTickets')->getData()) {
                foreach ($contractTickets as $ticket) {
                    $ticket->addContract($contract);
                }

                $ticketRepository->save($contractTickets, true);
            }

            if ($form->get('associateUnaccountedTimes')->getData()) {
                $timeSpents = [];

                foreach ($contractTickets as $ticket) {
                    $timeSpents = array_merge(
                        $timeSpents,
                        $ticket->getUnaccountedTimeSpents()->getValues()
                    );
                }

                $contractTimeAccounting->accountTimeSpents($contract, $timeSpents);
                $timeSpentRepository->save($timeSpents, true);
            }

            return $this->redirectToRoute('contract', [
                'uid' => $contract->getUid(),
            ]);
        }

        return $this->render('organizations/contracts/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }
}
