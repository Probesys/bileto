<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Ticket;
use App\Repository\AuthorizationRepository;
use App\Repository\OrganizationRepository;
use App\SearchEngine\TicketSearcher;
use App\Service;
use App\Service\Sorter\OrganizationSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PagesController extends BaseController
{
    #[Route('/', name: 'home', methods: ['GET', 'HEAD'])]
    public function home(
        AuthorizationRepository $authorizationRepository,
        OrganizationRepository $orgaRepository,
        OrganizationSorter $orgaSorter,
        TicketSearcher $ticketSearcher,
    ): Response {
        $ticketsPagination = $ticketSearcher->getTickets(TicketSearcher::queryOwned(), 'updated-desc', [
            'page' => 1,
            'maxResults' => 5,
        ]);

        return $this->render('pages/home.html.twig', [
            'ticketsPagination' => $ticketsPagination,
        ]);
    }

    #[Route('/about', name: 'about', methods: ['GET', 'HEAD'])]
    public function about(): Response
    {
        $version = $this->getParameter('app.version');

        return $this->render('pages/about.html.twig', [
            'version' => $version,
            'availableLanguages' => Service\Locales::SUPPORTED_LOCALES,
        ]);
    }

    #[Route('/app.manifest', name: 'webmanifest', methods: ['GET', 'HEAD'])]
    public function webmanifest(): Response
    {
        $response = $this->render('pages/webmanifest.json.twig');
        $response->headers->set('Content-Type', 'application/manifest+json');
        return $response;
    }

    #[Route('/advanced-search-syntax', name: 'advanced search syntax', methods: ['GET', 'HEAD'])]
    public function advancedSearchSyntax(): Response
    {
        return $this->render('pages/advanced_search_syntax.html.twig');
    }
}
