<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\OrganizationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TicketsController extends BaseController
{
    #[Route('/tickets/{uid}', name: 'ticket', methods: ['GET', 'HEAD'])]
    public function show(Ticket $ticket, OrganizationRepository $organizationRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $organization = $ticket->getOrganization();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $parentOrganizations = $organizationRepository->findParents($organization);
        $organization->setParentOrganizations($parentOrganizations);

        $statuses = Ticket::getStatusesWithLabels();
        if ($ticket->getStatus() !== 'new') {
            unset($statuses['new']);
        }

        return $this->render('tickets/show.html.twig', [
            'ticket' => $ticket,
            'messages' => $ticket->getMessages(),
            'organization' => $organization,
            'message' => '',
            'status' => 'pending',
            'statuses' => $statuses,
            'isSolution' => false,
            'isConfidential' => false,
        ]);
    }
}
