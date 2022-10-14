<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrganizationsController extends BaseController
{
    #[Route('/organizations', name: 'organizations', methods: ['GET', 'HEAD'])]
    public function index(OrganizationRepository $orgaRepository): Response
    {
        $organizations = $orgaRepository->findBy([], ['name' => 'ASC']);
        return $this->render('organizations/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/organizations/new', name: 'new organization', methods: ['GET', 'HEAD'])]
    public function new(): Response
    {
        return $this->render('organizations/new.html.twig', [
            'name' => '',
        ]);
    }

    #[Route('/organizations/new', name: 'create organization', methods: ['POST'])]
    public function create(
        Request $request,
        OrganizationRepository $orgaRepository,
        ValidatorInterface $validator
    ): Response {
        /** @var string $name */
        $name = $request->request->get('name', '');
        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create organization', $csrfToken)) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'name' => $name,
                'error' => $this->csrfError(),
            ]);
        }

        $organization = new Organization();
        $organization->setName($name);

        $errors = $validator->validate($organization);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'name' => $name,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $orgaRepository->save($organization, true);

        return $this->redirectToRoute('organizations');
    }
}
