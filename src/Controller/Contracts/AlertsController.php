<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Contracts;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Repository\ContractRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlertsController extends BaseController
{
    #[Route('/contracts/{uid:contract}/alerts/edit', name: 'edit contract alerts', methods: ['GET', 'HEAD'])]
    public function edit(
        Contract $contract,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        return $this->render('contracts/alerts/edit.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
            'hoursAlert' => $contract->getHoursAlert(),
            'dateAlert' => $contract->getDateAlert(),
        ]);
    }

    #[Route('/contracts/{uid:contract}/alerts/edit', name: 'update contract alerts', methods: ['POST'])]
    public function update(
        Contract $contract,
        Request $request,
        ContractRepository $contractRepository,
        TranslatorInterface $translator,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $hoursAlert = $request->request->getInt('hoursAlert');
        $dateAlert = $request->request->getInt('dateAlert');
        $csrfToken = $request->request->getString('_csrf_token');

        if ($hoursAlert < 0) {
            $hoursAlert = 0;
        } elseif ($hoursAlert > 100) {
            $hoursAlert = 100;
        }

        if ($dateAlert < 0) {
            $dateAlert = 0;
        } elseif ($dateAlert > $contract->getDaysDuration()) {
            $dateAlert = $contract->getDaysDuration();
        }

        if (!$this->isCsrfTokenValid('update contract alerts', $csrfToken)) {
            return $this->renderBadRequest('contracts/alerts/edit.html.twig', [
                'organization' => $organization,
                'contract' => $contract,
                'hoursAlert' => $hoursAlert,
                'dateAlert' => $dateAlert,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $contract->setHoursAlert($hoursAlert);
        $contract->setDateAlert($dateAlert);

        $contractRepository->save($contract, true);

        return $this->redirectToRoute('contract', [
            'uid' => $contract->getUid(),
        ]);
    }
}
