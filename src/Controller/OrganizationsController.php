<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use App\Security;
use App\Service;
use App\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OrganizationsController extends BaseController
{
    public function __construct(
        private readonly Repository\OrganizationRepository $organizationRepository,
        private readonly Service\Sorter\OrganizationSorter $orgaSorter,
        private readonly Security\Authorizer $authorizer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/organizations', name: 'organizations', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $organizations = $this->organizationRepository->findAuthorizedOrganizations($user);
        $this->orgaSorter->sort($organizations);

        $archivedOrganizations = $this->organizationRepository->findAuthorizedArchivedOrganizations($user);
        $archivedCount = count(array_filter(
            $archivedOrganizations,
            fn (Entity\Organization $org): bool => $this->authorizer->isGranted('orga:manage:archive', $org),
        ));

        return $this->render('organizations/index.html.twig', [
            'organizations' => $organizations,
            'archivedCount' => $archivedCount,
        ]);
    }

    #[Route('/organizations/new', name: 'new organization')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:create:organizations');

        $organization = new Entity\Organization();
        $form = $this->createNamedForm('organization', Form\OrganizationForm::class, $organization);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organization = $form->getData();
            $organization->normalizeDomains();
            $this->organizationRepository->save($organization, true);

            if ($this->authorizer->isGranted('orga:manage:contracts', $organization)) {
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
    public function show(Entity\Organization $organization): Response
    {
        $this->denyAccessUnlessGranted('orga:see', $organization);

        return $this->redirectToRoute('organization tickets', [
            'uid' => $organization->getUid(),
        ]);
    }

    #[Route('/organizations/{uid:organization}/settings', name: 'organization settings')]
    public function edit(Entity\Organization $organization, Request $request): Response
    {
        $canManage = $this->authorizer->isGranted('orga:manage', $organization);
        $canArchive = $this->authorizer->isGranted('orga:manage:archive', $organization);

        if (!$canManage && !$canArchive) {
            throw $this->createAccessDeniedException();
        }

        $form = null;
        if ($canManage) {
            $form = $this->createNamedForm('organization', Form\OrganizationForm::class, $organization);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $organization = $form->getData();
                $organization->normalizeDomains();
                $this->organizationRepository->save($organization, true);

                $this->addFlash('success', new TranslatableMessage('notifications.saved'));

                return $this->redirectToRoute('organization settings', [
                    'uid' => $organization->getUid(),
                ]);
            }
        }

        $archiveForm = null;
        if ($canArchive && !$organization->isArchived()) {
            $archiveForm = $this->createArchiveForm($organization);
        }

        return $this->render('organizations/settings.html.twig', [
            'organization' => $organization,
            'form' => $form,
            'archiveForm' => $archiveForm,
        ]);
    }

    #[Route('/organizations/{uid:organization}/archive', name: 'archive organization', methods: ['POST'])]
    public function archive(
        Entity\Organization $organization,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage:archive', $organization);

        if ($organization->isArchived()) {
            $this->addFlash('error', new TranslatableMessage(
                'organizations.settings.archive.already_archived'
            ));

            return $this->redirectToRoute('organization settings', [
                'uid' => $organization->getUid(),
            ]);
        }

        $archiveForm = $this->createArchiveForm($organization);
        $archiveForm->handleRequest($request);

        if ($archiveForm->isSubmitted() && $archiveForm->isValid()) {
            $organization->setArchivedAt(Utils\Time::now());

            /** @var Entity\User[] */
            $usersToArchive = $archiveForm->get('usersToArchive')->getData();
            foreach ($usersToArchive as $userToArchive) {
                $userToArchive->archive();
            }

            $entityManager->flush();

            $this->addFlash('success', new TranslatableMessage(
                'organizations.settings.archive.archived'
            ));

            return $this->redirectToRoute('organization tickets', [
                'uid' => $organization->getUid(),
            ]);
        }

        $form = null;
        if ($this->authorizer->isGranted('orga:manage', $organization)) {
            $form = $this->createNamedForm('organization', Form\OrganizationForm::class, $organization);
        }

        return $this->render('organizations/settings.html.twig', [
            'organization' => $organization,
            'form' => $form,
            'archiveForm' => $archiveForm,
        ]);
    }

    #[Route('/organizations/{uid:organization}/deletion', name: 'delete organization', methods: ['POST'])]
    public function delete(Entity\Organization $organization, Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:manage', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete organization', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('organizations');
        }

        $userOrganization = $user->getOrganization();
        if ($userOrganization && $userOrganization->getUid() === $organization->getUid()) {
            // Reset the current user default organization or Doctrine will
            // complain about an entity found.
            $user->setOrganization(null);
        }

        $this->organizationRepository->remove($organization, true);

        return $this->redirectToRoute('organizations');
    }

    /**
     * @return FormInterface<Entity\Organization>
     */
    private function createArchiveForm(Entity\Organization $organization): FormInterface
    {
        return $this->createNamedForm(
            'archive_organization',
            Form\ArchiveOrganizationForm::class,
            $organization,
            [
                'action' => $this->generateUrl('archive organization', [
                    'uid' => $organization->getUid(),
                ]),
            ],
        );
    }
}
