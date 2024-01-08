<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Contracts;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Repository\ContractRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class MaxHoursController extends BaseController
{
    #[Route('/contracts/{uid}/max-hours/edit', name: 'edit contract max hours', methods: ['GET', 'HEAD'])]
    public function edit(
        Contract $contract,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        return $this->render('contracts/max_hours/edit.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
            'additionalHours' => 0,
        ]);
    }

    #[Route('/contracts/{uid}/max-hours/edit', name: 'update contract max hours', methods: ['POST'])]
    public function update(
        Contract $contract,
        Request $request,
        ContractRepository $contractRepository,
        TranslatorInterface $translator,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $additionalHours = $request->request->getInt('additionalHours');
        $csrfToken = $request->request->getString('_csrf_token');

        if ($additionalHours < 0) {
            $additionalHours = 0;
        }

        if (!$this->isCsrfTokenValid('update contract max hours', $csrfToken)) {
            return $this->renderBadRequest('contracts/max_hours/edit.html.twig', [
                'organization' => $organization,
                'contract' => $contract,
                'additionalHours' => $additionalHours,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $maxHours = $contract->getMaxHours() + $additionalHours;
        $contract->setMaxHours($maxHours);

        $contractRepository->save($contract, true);

        return $this->redirectToRoute('organization contract', [
            'uid' => $organization->getUid(),
            'contract_uid' => $contract->getUid(),
        ]);
    }
}
