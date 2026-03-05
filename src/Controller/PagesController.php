<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\SearchEngine;
use App\Security;
use App\Service;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PagesController extends BaseController
{
    public function __construct(
        private readonly SearchEngine\Ticket\Searcher $ticketSearcher,
        private readonly Security\Authorizer $authorizer,
        private readonly Service\UserService $userService,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET', 'HEAD'])]
    public function home(): Response
    {
        $ticketsOwnedPagination = $this->ticketSearcher->getTickets(
            SearchEngine\Ticket\Searcher::queryOwned(),
            'updated-desc',
            [
                'page' => 1,
                'maxResults' => 5,
            ]
        );
        if ($this->authorizer->isAgent('any')) {
            $ticketsAssignedPagination = $this->ticketSearcher->getTickets(
                SearchEngine\Ticket\Searcher::queryAssignedMe(),
                'updated-desc',
                [
                    'page' => 1,
                    'maxResults' => 5,
                ]
            );
        } else {
            $ticketsAssignedPagination = null;
        }

        /** @var Entity\User */
        $user = $this->getUser();
        $defaultOrganization = $this->userService->getDefaultOrganization($user);

        return $this->render('pages/home.html.twig', [
            'ticketsOwnedPagination' => $ticketsOwnedPagination,
            'ticketsAssignedPagination' => $ticketsAssignedPagination,
            'defaultOrganization' => $defaultOrganization,
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
    public function advancedSearchSyntax(Request $request): Response
    {
        $supportedSubjects = ['tickets', 'contracts'];
        $subject = $request->query->getString('subject');

        if (!in_array($subject, $supportedSubjects)) {
            throw $this->createNotFoundException('The subject is not supported');
        }

        return $this->render("pages/advanced_search_syntax/{$subject}.html.twig");
    }
}
