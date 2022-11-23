<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Ticket;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TicketsController extends BaseController
{
    #[Route('/tickets/{uid}', name: 'ticket', methods: ['GET', 'HEAD'])]
    public function show(Ticket $ticket): Response
    {
        return $this->render('tickets/show.html.twig', [
            'ticket' => $ticket,
            'messages' => $ticket->getMessages(),
            'organization' => $ticket->getOrganization(),
            'message' => '',
            'status' => 'pending',
            'statuses' => Ticket::getStatusesWithLabels(),
            'isSolution' => false,
            'isConfidential' => false,
        ]);
    }
}
