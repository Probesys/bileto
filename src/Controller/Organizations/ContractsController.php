<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Contract;
use App\Entity\Organization;
use App\Repository\ContractRepository;
use App\Repository\OrganizationRepository;
use App\Service\Sorter\ContractSorter;
use App\Utils;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContractsController extends BaseController
{
    #[Route('/organizations/{uid}/contracts', name: 'organization contracts', methods: ['GET', 'HEAD'])]
    public function index(
        Organization $organization,
        ContractRepository $contractRepository,
        OrganizationRepository $organizationRepository,
        ContractSorter $contractSorter,
        Security $security,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // We want to list the contracts from the parent organizations as well
        // as they apply to this organization as well.
        $parentOrganizationIds = $organization->getParentOrganizationIds();
        $parentOrganizations = $organizationRepository->findBy([
            'id' => $parentOrganizationIds,
        ]);

        $allowedOrganizations = [$organization];
        foreach ($parentOrganizations as $parentOrganization) {
            if ($security->isGranted('orga:see:contracts', $parentOrganization)) {
                $allowedOrganizations[] = $parentOrganization;
            }
        }

        $contracts = $contractRepository->findBy([
            'organization' => $allowedOrganizations,
        ]);
        $contractSorter->sort($contracts);

        return $this->render('organizations/contracts/index.html.twig', [
            'organization' => $organization,
            'contracts' => $contracts,
        ]);
    }

    #[Route('/organizations/{uid}/contracts/new', name: 'new organization contract', methods: ['GET', 'HEAD'])]
    public function new(
        Organization $organization,
        Request $request,
        ContractRepository $contractRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $fromContractUid = $request->query->getString('from');

        $contract = null;
        if ($fromContractUid) {
            $contract = $contractRepository->findOneBy([
                'uid' => $fromContractUid,
            ]);
        }

        if ($contract) {
            $name = $contract->getRenewedName();
            $maxHours = $contract->getMaxHours();
            $startAt = $contract->getEndAt()->modify('+1 day');
            $endAt = $startAt->modify('last day of december');
            $billingInterval = $contract->getBillingInterval();
            $notes = $contract->getNotes();
        } else {
            $name = '';
            $maxHours = 10;
            $startAt = Utils\Time::now();
            $endAt = Utils\Time::relative('last day of december');
            $billingInterval = 0;
            $notes = '';
        }

        return $this->render('organizations/contracts/new.html.twig', [
            'organization' => $organization,
            'name' => $name,
            'maxHours' => $maxHours,
            'startAt' => $startAt,
            'endAt' => $endAt,
            'billingInterval' => $billingInterval,
            'notes' => $notes,
        ]);
    }

    #[Route('/organizations/{uid}/contracts/new', name: 'create organization contract', methods: ['POST'])]
    public function create(
        Organization $organization,
        Request $request,
        ContractRepository $contractRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $name = trim($request->request->getString('name'));
        $maxHours = $request->request->getInt('maxHours');
        $startAt = $request->request->getString('startAt');
        $endAt = $request->request->getString('endAt');
        $billingInterval = $request->request->getInt('billingInterval');
        $notes = trim($request->request->getString('notes'));
        $csrfToken = $request->request->getString('_csrf_token');

        $startAt = \DateTimeImmutable::createFromFormat('Y-m-d', $startAt);
        $endAt = \DateTimeImmutable::createFromFormat('Y-m-d', $endAt);

        if (!$this->isCsrfTokenValid('create organization contract', $csrfToken)) {
            return $this->renderBadRequest('organizations/contracts/new.html.twig', [
                'organization' => $organization,
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'billingInterval' => $billingInterval,
                'notes' => $notes,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $contract = new Contract();
        $contract->setOrganization($organization);
        $contract->setName($name);
        $contract->setMaxHours($maxHours);
        $contract->setBillingInterval($billingInterval);
        $contract->setNotes($notes);

        if ($startAt) {
            $contract->setStartAt($startAt);
        }

        if ($endAt) {
            $contract->setEndAt($endAt);
        }

        $errors = $validator->validate($contract);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/contracts/new.html.twig', [
                'organization' => $organization,
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => $startAt,
                'endAt' => $endAt,
                'billingInterval' => $billingInterval,
                'notes' => $notes,
                'errors' => Utils\ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $contractRepository->save($contract, true);

        return $this->redirectToRoute('organization contracts', [
            'uid' => $organization->getUid(),
        ]);
    }

    #[Route('/organizations/{uid}/contracts/{contract_uid}', name: 'organization contract', methods: ['GET', 'HEAD'])]
    public function show(
        Organization $organization,
        #[MapEntity(mapping: ['contract_uid' => 'uid'])]
        Contract $contract,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        return $this->render('organizations/contracts/show.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
        ]);
    }
}
