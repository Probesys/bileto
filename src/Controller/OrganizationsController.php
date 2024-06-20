<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Organization;
use App\Form\Type\OrganizationType;
use App\Repository\OrganizationRepository;
use App\Security\Authorizer;
use App\Service\Sorter\OrganizationSorter;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Time;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrganizationsController extends BaseController
{
    #[Route('/organizations', name: 'organizations', methods: ['GET', 'HEAD'])]
    public function index(
        OrganizationRepository $orgaRepository,
        OrganizationSorter $orgaSorter,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $organizations = $orgaRepository->findAuthorizedOrganizations($user);
        $orgaSorter->sort($organizations);

        return $this->render('organizations/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/organizations/new', name: 'new organization', methods: ['GET', 'HEAD'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('admin:create:organizations');

        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);

        return $this->render('organizations/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/organizations/new', name: 'create organization', methods: ['POST'])]
    public function create(
        Request $request,
        OrganizationRepository $orgaRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:create:organizations');

        $form = $this->createForm(OrganizationType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'form' => $form,
            ]);
        }

        $organization = $form->getData();
        $organization->normalizeDomains();
        $orgaRepository->save($organization, true);

        return $this->redirectToRoute('organizations');
    }

    #[Route('/organizations/{uid}', name: 'organization', methods: ['GET', 'HEAD'])]
    public function show(
        Organization $organization,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see', $organization);

        return $this->redirectToRoute('organization tickets', [
            'uid' => $organization->getUid(),
        ]);
    }

    #[Route('/organizations/{uid}/settings', name: 'organization settings', methods: ['GET', 'HEAD'])]
    public function settings(Organization $organization): Response
    {
        $this->denyAccessUnlessGranted('orga:manage', $organization);

        $form = $this->createForm(OrganizationType::class, $organization);

        return $this->render('organizations/settings.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/organizations/{uid}/settings', name: 'update organization', methods: ['POST'])]
    public function update(
        Organization $organization,
        Request $request,
        OrganizationRepository $orgaRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage', $organization);

        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('organizations/settings.html.twig', [
                'organization' => $organization,
                'form' => $form,
            ]);
        }

        $organization = $form->getData();
        $organization->normalizeDomains();
        $orgaRepository->save($organization, true);

        $this->addFlash('success', new TranslatableMessage('notifications.saved'));

        return $this->redirectToRoute('organization settings', [
            'uid' => $organization->getUid(),
        ]);
    }

    #[Route('/organizations/{uid}/deletion', name: 'delete organization', methods: ['POST'])]
    public function delete(
        Organization $organization,
        Request $request,
        OrganizationRepository $organizationRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete organization', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('organizations');
        }

        $userOrganization = $user->getOrganization();
        if ($userOrganization && $userOrganization->getUid() === $organization->getUid()) {
            // Reset the current user default organization or Doctrine will
            // complain about an entity found.
            $user->setOrganization(null);
        }

        $organizationRepository->remove($organization, true);

        return $this->redirectToRoute('organizations');
    }
}
