<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TimeSpentsController extends BaseController
{
    #[Route('/time-spents/{uid:timeSpent}/edit', name: 'edit time spent')]
    public function edit(
        Entity\TimeSpent $timeSpent,
        Request $request,
        Repository\TimeSpentRepository $timeSpentRepository,
        Service\ContractTimeAccounting $contractTimeAccounting,
    ): Response {
        $ticket = $timeSpent->getTicket();

        $this->denyAccessUnlessGranted('orga:create:tickets:time_spent', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('time_spent', Form\TimeSpentForm::class, $timeSpent);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $timeSpent = $form->getData();

            if ($timeSpent->getRealTime() === 0) {
                $timeSpentRepository->remove($timeSpent, true);
            } else {
                $contract = $timeSpent->getContract();

                if ($contract && $timeSpent->mustNotBeAccounted()) {
                    $contractTimeAccounting->unaccountTimeSpents([$timeSpent]);
                } elseif ($contract) {
                    $contractTimeAccounting->reaccountTimeSpents($contract, [$timeSpent]);
                }

                $timeSpentRepository->save($timeSpent, true);
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
