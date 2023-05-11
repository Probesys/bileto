<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use App\Service\OrganizationSorter;
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
        $countOrganizations = count($organizations);
        $organizations = $orgaSorter->asTree($organizations);

        return $this->render('organizations/index.html.twig', [
            'organizations' => $organizations,
            'countOrganizations' => $countOrganizations,
        ]);
    }

    #[Route('/organizations/new', name: 'new organization', methods: ['GET', 'HEAD'])]
    public function new(
        Request $request,
        OrganizationRepository $orgaRepository,
        OrganizationSorter $orgaSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        /** @var string|null $parentUid */
        $parentUid = $request->query->get('parent');
        if ($parentUid !== null) {
            $parentOrganization = $orgaRepository->findOneBy(['uid' => $parentUid]);
            $organizations = $orgaRepository->findWithSubOrganizations(
                [$parentOrganization->getId()]
            );
            $organizations = $orgaSorter->asTree($organizations, Organization::MAX_DEPTH - 1);
        } else {
            $parentOrganization = null;
            $organizations = [];
        }

        return $this->render('organizations/new.html.twig', [
            'parentOrganization' => $parentOrganization,
            'organizations' => $organizations,
            'name' => '',
            'selectedParentUid' => '',
        ]);
    }

    #[Route('/organizations/new', name: 'create organization', methods: ['POST'])]
    public function create(
        Request $request,
        OrganizationRepository $orgaRepository,
        OrganizationSorter $orgaSorter,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:organizations');

        /** @var string|null $parentUid */
        $parentUid = $request->query->get('parent');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $selectedParentUid */
        $selectedParentUid = $request->request->get('selectedParent', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if ($parentUid !== null) {
            $parentOrganization = $orgaRepository->findOneBy(['uid' => $parentUid]);
            $organizations = $orgaRepository->findWithSubOrganizations(
                [$parentOrganization->getId()]
            );
            $organizations = $orgaSorter->asTree($organizations, Organization::MAX_DEPTH - 1);
        } else {
            $parentOrganization = null;
            $organizations = [];
        }

        if (!$this->isCsrfTokenValid('create organization', $csrfToken)) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'parentOrganization' => $parentOrganization,
                'organizations' => $organizations,
                'name' => $name,
                'selectedParentUid' => $selectedParentUid,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $organization = new Organization();
        $organization->setName($name);

        if ($selectedParentUid) {
            $selectedParentOrganization = $orgaRepository->findOneBy([
                'uid' => $selectedParentUid,
            ]);

            if (!$selectedParentOrganization) {
                return $this->renderBadRequest('organizations/new.html.twig', [
                    'parentOrganization' => $parentOrganization,
                    'organizations' => $organizations,
                    'name' => $name,
                    'selectedParentUid' => $selectedParentUid,
                    'errors' => [
                        'parentsPath' => $translator->trans('organization.sub.invalid', [], 'errors'),
                    ],
                ]);
            }

            $organization->setParent($selectedParentOrganization);
        }

        $errors = $validator->validate($organization);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'parentOrganization' => $parentOrganization,
                'organizations' => $organizations,
                'name' => $name,
                'selectedParentUid' => $selectedParentUid,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $orgaRepository->save($organization, true);

        return $this->redirectToRoute('organizations');
    }

    #[Route('/organizations/{uid}', name: 'organization', methods: ['GET', 'HEAD'])]
    public function show(Organization $organization): Response
    {
        $this->denyAccessUnlessGranted('orga:see', $organization);

        return $this->redirectToRoute('organization tickets', [
            'uid' => $organization->getUid(),
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
                'errors' => $this->formatErrors($errors),
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

        $organizations = $organizationRepository->findSubOrganizations($organization);
        $organizations[] = $organization;
        $organizationRepository->removeInBatch($organizations);

        return $this->redirectToRoute('organizations');
    }
}
