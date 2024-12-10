<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Repository;
use App\SearchEngine;
use App\Security;
use App\Service;
use App\Service\Sorter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PagesController extends BaseController
{
    #[Route('/', name: 'home', methods: ['GET', 'HEAD'])]
    public function home(
        Repository\AuthorizationRepository $authorizationRepository,
        Repository\OrganizationRepository $orgaRepository,
        Sorter\OrganizationSorter $orgaSorter,
        SearchEngine\Ticket\Searcher $ticketSearcher,
        Security\Authorizer $authorizer,
        Service\UserService $userService,
    ): Response {
        if ($authorizer->isAgent('any')) {
            $query = SearchEngine\Ticket\Searcher::queryAssignedMe();
            $view = 'assigned-me';
        } else {
            $query = SearchEngine\Ticket\Searcher::queryOwned();
            $view = 'owned';
        }

        $ticketsPagination = $ticketSearcher->getTickets($query, 'updated-desc', [
            'page' => 1,
            'maxResults' => 5,
        ]);

        /** @var Entity\User */
        $user = $this->getUser();
        $defaultOrganization = $userService->getDefaultOrganization($user);

        return $this->render('pages/home.html.twig', [
            'view' => $view,
            'ticketsPagination' => $ticketsPagination,
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
