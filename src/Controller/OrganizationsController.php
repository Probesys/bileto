<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
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
    public function new(
        Request $request,
        OrganizationRepository $orgaRepository,
    ): Response {
        /** @var string|null $parentUid */
        $parentUid = $request->query->get('parent');
        if ($parentUid !== null) {
            $parentOrganization = $orgaRepository->findOneByAsTree(
                ['uid' => $parentUid],
                Organization::MAX_DEPTH - 1,
            );
        } else {
            $parentOrganization = null;
        }

        return $this->render('organizations/new.html.twig', [
            'parentOrganization' => $parentOrganization,
            'name' => '',
            'selectedParentUid' => '',
        ]);
    }

    #[Route('/organizations/new', name: 'create organization', methods: ['POST'])]
    public function create(
        Request $request,
        OrganizationRepository $orgaRepository,
        ValidatorInterface $validator
    ): Response {
        /** @var string|null $parentUid */
        $parentUid = $request->query->get('parent');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $selectedParentUid */
        $selectedParentUid = $request->request->get('selectedParent', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if ($parentUid !== null) {
            $parentOrganization = $orgaRepository->findOneByAsTree(
                ['uid' => $parentUid],
                Organization::MAX_DEPTH - 1,
            );
        } else {
            $parentOrganization = null;
        }

        if (!$this->isCsrfTokenValid('create organization', $csrfToken)) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'parentOrganization' => $parentOrganization,
                'name' => $name,
                'selectedParentUid' => $selectedParentUid,
                'error' => $this->csrfError(),
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
                    'name' => $name,
                    'selectedParentUid' => $selectedParentUid,
                    'errors' => [
                        'parentsPath' => new TranslatableMessage(
                            'Select an organization from this list.',
                            [],
                            'validators',
                        ),
                    ],
                ]);
            }

            $organization->setParent($selectedParentOrganization);
        }

        $uid = $orgaRepository->generateUid();
        $organization->setUid($uid);

        $errors = $validator->validate($organization);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/new.html.twig', [
                'parentOrganization' => $parentOrganization,
                'name' => $name,
                'selectedParentUid' => $selectedParentUid,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $orgaRepository->save($organization, true);

        return $this->redirectToRoute('organization', [
            'uid' => $organization->getUid(),
        ]);
    }

    #[Route('/organizations/{uid}', name: 'organization', methods: ['GET', 'HEAD'])]
    public function show(Organization $organization): Response
    {
        return $this->redirectToRoute('organization tickets', [
            'uid' => $organization->getUid(),
        ]);
    }
}
