<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Form\Type\ContractType;
use App\Repository\ContractRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContractsController extends BaseController
{
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
