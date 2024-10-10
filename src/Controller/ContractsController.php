<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Form;
use App\Repository\ContractRepository;
use App\Repository\OrganizationRepository;
use App\Security\Authorizer;
use App\Utils\Pagination;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ContractsController extends BaseController
{
    #[Route('/contracts', name: 'contracts', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        ContractRepository $contractRepository,
        OrganizationRepository $organizationRepository,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', 'any');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $page = $request->query->getInt('page', 1);

        $organizations = $organizationRepository->findAuthorizedOrganizations($user);
        $authorizedOrganizations = [];
        foreach ($organizations as $organization) {
            if ($authorizer->isGranted('orga:see:contracts', $organization)) {
                $authorizedOrganizations[] = $organization;
            }
        }

        $contractsQuery = $contractRepository->findOngoingByOrganizationsQuery($authorizedOrganizations);

        $contractsPagination = Pagination::paginate($contractsQuery, [
            'page' => $page,
            'maxResults' => 25,
        ]);

        return $this->render('contracts/index.html.twig', [
            'contractsPagination' => $contractsPagination,
        ]);
    }

    #[Route('/contracts/{uid:contract}', name: 'contract', methods: ['GET', 'HEAD'])]
    public function show(Contract $contract): Response
    {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        return $this->render('contracts/show.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
        ]);
    }

    #[Route('/contracts/{uid:contract}/edit', name: 'edit contract')]
    public function edit(
        Contract $contract,
        Request $request,
        ContractRepository $contractRepository,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $form = $this->createNamedForm('contract', Form\ContractForm::class, $contract, [
            'allow_associate' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract = $form->getData();
            $contractRepository->save($contract, true);

            return $this->redirectToRoute('contract', [
                'uid' => $contract->getUid(),
            ]);
        }

        return $this->render('contracts/edit.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
            'form' => $form,
        ]);
    }
}
