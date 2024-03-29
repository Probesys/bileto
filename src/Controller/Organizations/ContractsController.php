<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Entity\Organization;
use App\Form\Type\ContractType;
use App\Repository\ContractRepository;
use App\Repository\OrganizationRepository;
use App\Security\Authorizer;
use App\Service\Sorter\ContractSorter;
use App\Utils;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContractsController extends BaseController
{
    #[Route('/organizations/{uid}/contracts', name: 'organization contracts', methods: ['GET', 'HEAD'])]
    public function index(
        Organization $organization,
        ContractRepository $contractRepository,
        OrganizationRepository $organizationRepository,
        ContractSorter $contractSorter,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $contracts = $contractRepository->findBy([
            'organization' => $organization,
        ]);
        $contractSorter->sort($contracts);

        return $this->render('organizations/contracts/index.html.twig', [
            'organization' => $organization,
            'contracts' => $contracts,
        ]);
    }

    #[Route('/organizations/{uid}/contracts/new', name: 'new organization contract', methods: ['GET', 'HEAD'])]
    public function new(
        Organization $organization,
        Request $request,
        ContractRepository $contractRepository,
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

        $form = $this->createForm(ContractType::class, $contract);

        return $this->render('organizations/contracts/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/organizations/{uid}/contracts/new', name: 'create organization contract', methods: ['POST'])]
    public function create(
        Organization $organization,
        Request $request,
        ContractRepository $contractRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $form = $this->createForm(ContractType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('organizations/contracts/new.html.twig', [
                'organization' => $organization,
                'form' => $form,
            ]);
        }

        $contract = $form->getData();
        $contract->setOrganization($organization);
        $contract->initDefaultAlerts();
        $contractRepository->save($contract, true);

        return $this->redirectToRoute('contract', [
            'uid' => $contract->getUid(),
        ]);
    }
}
