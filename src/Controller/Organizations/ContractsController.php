<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Organization;
use App\Repository\ContractRepository;
use App\Repository\OrganizationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ContractsController extends BaseController
{
    #[Route('/organizations/{uid}/contracts', name: 'organization contracts', methods: ['GET', 'HEAD'])]
    public function index(
        Organization $organization,
        Request $request,
        ContractRepository $contractRepository,
        OrganizationRepository $organizationRepository,
        Security $security,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // We want to list the contracts from the parent organizations as well
        // as they apply to this organization as well.
        $parentOrganizationIds = $organization->getParentOrganizationIds();
        $parentOrganizations = $organizationRepository->findBy([
            'id' => $parentOrganizationIds,
        ]);

        $allowedOrganizations = [$organization];
        foreach ($parentOrganizations as $parentOrganization) {
            if ($security->isGranted('orga:see:contracts', $parentOrganization)) {
                $allowedOrganizations[] = $parentOrganization;
            }
        }

        $contracts = $contractRepository->findBy([
            'organization' => $allowedOrganizations,
        ], ['endAt' => 'DESC']);

        return $this->render('organizations/contracts/index.html.twig', [
            'organization' => $organization,
            'contracts' => $contracts,
        ]);
    }
}
