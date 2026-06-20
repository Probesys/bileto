<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity;
use App\Repository;
use App\Security;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArchivesController extends BaseController
{
    public function __construct(
        private readonly Repository\OrganizationRepository $organizationRepository,
        private readonly Service\Sorter\OrganizationSorter $orgaSorter,
        private readonly Security\Authorizer $authorizer,
    ) {
    }

    #[Route('/organizations/archived', name: 'archived organizations', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        /** @var Entity\User $user */
        $user = $this->getUser();

        $organizations = $this->organizationRepository->findAuthorizedArchivedOrganizations($user);

        $organizations = array_values(array_filter(
            $organizations,
            fn (Entity\Organization $org): bool => $this->authorizer->isGranted('orga:manage:archive', $org),
        ));

        $this->orgaSorter->sort($organizations);

        return $this->render('organizations/archives/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }
}
