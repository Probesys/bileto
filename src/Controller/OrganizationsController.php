<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Organization;
use App\Form;
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
        OrganizationRepository $organizationRepository,
        OrganizationSorter $orgaSorter,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $organizations = $organizationRepository->findAuthorizedOrganizations($user);
        $orgaSorter->sort($organizations);

        return $this->render('organizations/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/organizations/new', name: 'new organization')]
    public function new(
        Request $request,
        OrganizationRepository $organizationRepository,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('admin:create:organizations');

        $organization = new Organization();
        $form = $this->createNamedForm('organization', Form\OrganizationForm::class, $organization);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organization = $form->getData();
            $organization->normalizeDomains();
            $organizationRepository->save($organization, true);

            if ($authorizer->isGranted('orga:manage:contracts', $organization)) {
                return $this->redirectToRoute('new organization contract', [
                    'uid' => $organization->getUid(),
                ]);
            } else {
                return $this->redirectToRoute('organizations');
            }
        }

        return $this->render('organizations/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/organizations/{uid:organization}', name: 'organization', methods: ['GET', 'HEAD'])]
    public function show(
        Organization $organization,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see', $organization);

        return $this->redirectToRoute('organization tickets', [
            'uid' => $organization->getUid(),
        ]);
    }

    #[Route('/organizations/{uid:organization}/settings', name: 'organization settings')]
    public function edit(
        Organization $organization,
        Request $request,
        OrganizationRepository $organizationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage', $organization);

        $form = $this->createNamedForm('organization', Form\OrganizationForm::class, $organization);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organization = $form->getData();
            $organization->normalizeDomains();
            $organizationRepository->save($organization, true);

            $this->addFlash('success', new TranslatableMessage('notifications.saved'));

            return $this->redirectToRoute('organization settings', [
                'uid' => $organization->getUid(),
            ]);
        }

        return $this->render('organizations/settings.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }

    #[Route('/organizations/{uid:organization}/deletion', name: 'delete organization', methods: ['POST'])]
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
