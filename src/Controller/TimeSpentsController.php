<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TimeSpentsController extends BaseController
{
    public function __construct(
        private readonly Repository\TimeSpentRepository $timeSpentRepository,
        private readonly Service\ContractTimeAccounting $contractTimeAccounting,
    ) {
    }

    #[Route('/time-spents/{uid:timeSpent}/edit', name: 'edit time spent')]
    public function edit(Entity\TimeSpent $timeSpent, Request $request): Response
    {
        $ticket = $timeSpent->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:time_spent', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('time_spent', Form\TimeSpentForm::class, $timeSpent);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $timeSpent = $form->getData();

            if ($timeSpent->getRealTime() === 0) {
                $this->timeSpentRepository->remove($timeSpent, true);
            } else {
                $contract = $timeSpent->getContract();

                if ($contract && $timeSpent->mustNotBeAccounted()) {
                    $this->contractTimeAccounting->unaccountTimeSpents([$timeSpent]);
                } elseif ($contract) {
                    $this->contractTimeAccounting->reaccountTimeSpents($contract, [$timeSpent]);
                }

                $this->timeSpentRepository->save($timeSpent, true);
            }

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('time_spents/edit.html.twig', [
            'timeSpent' => $timeSpent,
            'form' => $form,
        ]);
    }
}
