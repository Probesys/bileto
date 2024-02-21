<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use App\Security\Authorizer;
use App\Service\Sorter\OrganizationSorter;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Time;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrganizationsController extends BaseController
{
    #[Route('/organizations', name: 'organizations', methods: ['GET', 'HEAD'])]
    public function index(
        OrganizationRepository $orgaRepository,
        OrganizationSorter $orgaSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        $organizations = $orgaRepository->findAll();
        $orgaSorter->sort($organizations);

        return $this->render('organizations/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/organizations/new', name: 'new organization', methods: ['GET', 'HEAD'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        return $this->render('organizations/new.html.twig', [
            'name' => '',
        ]);
    }

    #[Route('/organizations/new', name: 'create organization', methods: ['POST'])]
    public function create(
        Request $request,
        OrganizationRepository $orgaRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create organization', $csrfToken)) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'name' => $name,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $organization = new Organization();
        $organization->setName($name);

        $errors = $validator->validate($organization);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'name' => $name,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $orgaRepository->save($organization, true);

        return $this->redirectToRoute('organizations');
    }

    #[Route('/organizations/{uid}', name: 'organization', methods: ['GET', 'HEAD'])]
    public function show(
        Organization $organization,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see', $organization);

        if (!$authorizer->isGranted('orga:see:contracts', $organization)) {
            return $this->redirectToRoute('organization tickets', [
                'uid' => $organization->getUid(),
            ]);
        }

        return $this->render('organizations/show.html.twig', [
            'organization' => $organization,
        ]);
    }

    #[Route('/organizations/{uid}/edit', name: 'edit organization', methods: ['GET', 'HEAD'])]
    public function edit(Organization $organization): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        return $this->render('organizations/edit.html.twig', [
            'organization' => $organization,
            'name' => $organization->getName(),
        ]);
    }

    #[Route('/organizations/{uid}/edit', name: 'update organization', methods: ['POST'])]
    public function update(
        Organization $organization,
        Request $request,
        OrganizationRepository $orgaRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update organization', $csrfToken)) {
            return $this->renderBadRequest('organizations/edit.html.twig', [
                'organization' => $organization,
                'name' => $name,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $organization->setName($name);

        $errors = $validator->validate($organization);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/edit.html.twig', [
                'organization' => $organization,
                'name' => $name,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $orgaRepository->save($organization, true);

        return $this->redirectToRoute('organizations');
    }

    #[Route('/organizations/{uid}/deletion', name: 'deletion organization', methods: ['GET', 'HEAD'])]
    public function deletion(Organization $organization): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        return $this->render('organizations/deletion.html.twig', [
            'organization' => $organization,
        ]);
    }

    #[Route('/organizations/{uid}/deletion', name: 'delete organization', methods: ['POST'])]
    public function delete(
        Organization $organization,
        Request $request,
        OrganizationRepository $organizationRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete organization', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('organizations');
        }

        $organizationRepository->remove($organization, true);

        return $this->redirectToRoute('organizations');
    }
}
