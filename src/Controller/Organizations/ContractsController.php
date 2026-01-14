<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\SearchEngine;
use App\Service;
use App\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ContractsController extends BaseController
{
    #[Route('/organizations/{uid:organization}/contracts', name: 'organization contracts', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        Entity\Organization $organization,
        SearchEngine\Contract\Searcher $contractSearcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        $page = $request->query->getInt('page', 1);
        $sort = $request->query->getString('sort', 'end-desc');

        $advancedSearchForm = $this->createNamedForm('', Form\AdvancedSearchForm::class, [
            'q' => SearchEngine\Contract\Searcher::queryDefault(),
        ]);
        $advancedSearchForm->handleRequest($request);

        $query = $advancedSearchForm->get('q')->getData();

        $contractSearcher->setOrganization($organization);

        if ($query) {
            $contractsPagination = $contractSearcher->getContracts($query, $sort, [
                'page' => $page,
                'maxResults' => 25,
            ]);
        } else {
            $contractsPagination = Utils\Pagination::empty();
        }

        return $this->render('organizations/contracts/index.html.twig', [
            'organization' => $organization,
            'contractsPagination' => $contractsPagination,
            'query' => $query?->getString(),
            'sort' => $sort,
            'advancedSearchForm' => $advancedSearchForm,
        ]);
    }

    #[Route('/organizations/{uid:organization}/contracts/new', name: 'new organization contract')]
    public function new(
        Entity\Organization $organization,
        Request $request,
        Repository\ContractRepository $contractRepository,
        Repository\TicketRepository $ticketRepository,
        Repository\TimeSpentRepository $timeSpentRepository,
        Service\ContractTimeAccounting $contractTimeAccounting,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $fromContractUid = $request->query->getString('from');

        $renewedContract = null;
        if ($fromContractUid) {
            $renewedContract = $contractRepository->findOneBy([
                'uid' => $fromContractUid,
            ]);
        }

        if ($renewedContract && $renewedContract->getRenewedBy()) {
            throw $this->createAccessDeniedException('You cannot renew a contract that has already been renewed');
        }

        if ($renewedContract) {
            $contract = $renewedContract->getRenewed();
        } else {
            $contract = new Entity\Contract();
        }

        $form = $this->createNamedForm('contract', Form\ContractForm::class, $contract, [
            'allow_associate' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract = $form->getData();
            $contract->setOrganization($organization);
            $contract->initDefaultAlerts();

            if ($renewedContract) {
                $renewedContract->setRenewedBy($contract);

                $contractRepository->save($renewedContract);
            }

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
