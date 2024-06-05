<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Form\Type\ContractType;
use App\Repository\ContractRepository;
use App\Repository\OrganizationRepository;
use App\Security\Authorizer;
use App\Utils\Pagination;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    #[Route('/contracts/{uid}', name: 'contract', methods: ['GET', 'HEAD'])]
    public function show(Contract $contract): Response
    {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        return $this->render('contracts/show.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
        ]);
    }

    #[Route('/contracts/{uid}/edit', name: 'edit contract', methods: ['GET', 'HEAD'])]
    public function edit(
        Contract $contract,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $form = $this->createForm(ContractType::class, $contract);

        return $this->render('contracts/edit.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
            'form' => $form,
        ]);
    }

    #[Route('/contracts/{uid}/edit', name: 'update contract', methods: ['POST'])]
    public function update(
        Contract $contract,
        Request $request,
        ContractRepository $contractRepository,
        TranslatorInterface $translator,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $form = $this->createForm(ContractType::class, $contract);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('contracts/edit.html.twig', [
                'organization' => $organization,
                'contract' => $contract,
                'form' => $form,
            ]);
        }

        $contract = $form->getData();
        $contractRepository->save($contract, true);

        return $this->redirectToRoute('contract', [
            'uid' => $contract->getUid(),
        ]);
    }
}
