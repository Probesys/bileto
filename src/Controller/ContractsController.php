<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\SearchEngine;
use App\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class ContractsController extends BaseController
{
    #[Route('/contracts', name: 'contracts', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        SearchEngine\Contract\Searcher $contractSearcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:contracts', 'any');

        $page = $request->query->getInt('page', 1);
        $sort = $request->query->getString('sort', 'end-desc');

        $advancedSearchForm = $this->createNamedForm('', Form\AdvancedSearchForm::class, [
            'q' => SearchEngine\Contract\Searcher::queryDefault(),
        ]);
        $advancedSearchForm->handleRequest($request);

        $query = $advancedSearchForm->get('q')->getData();

        if ($query) {
            $contractsPagination = $contractSearcher->getContracts($query, $sort, [
                'page' => $page,
                'maxResults' => 25,
            ]);
        } else {
            $contractsPagination = Utils\Pagination::empty();
        }

        return $this->render('contracts/index.html.twig', [
            'contractsPagination' => $contractsPagination,
            'query' => $query?->getString(),
            'sort' => $sort,
            'advancedSearchForm' => $advancedSearchForm,
        ]);
    }

    #[Route('/contracts/new', name: 'new contract')]
    public function new(
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('orga:manage:contracts', 'any');

        $form = $this->createNamedForm('contract', Form\Organization\SelectForm::class, options: [
            'permission' => 'orga:manage:contracts',
            'submit_label' => new TranslatableMessage('contracts.new.open_contract'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organization = $form->getData()['organization'];

            return $this->redirectToRoute('new organization contract', [
                'uid' => $organization->getUid(),
            ]);
        }

        return $this->render('contracts/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/contracts/{uid:contract}', name: 'contract', methods: ['GET', 'HEAD'])]
    public function show(Entity\Contract $contract): Response
    {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:see:contracts', $organization);

        return $this->render('contracts/show.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
        ]);
    }

    #[Route('/contracts/{uid:contract}/edit', name: 'edit contract')]
    public function edit(
        Entity\Contract $contract,
        Request $request,
        Repository\ContractRepository $contractRepository,
    ): Response {
        $organization = $contract->getOrganization();

        $this->denyAccessUnlessGranted('orga:manage:contracts', $organization);

        $form = $this->createNamedForm('contract', Form\ContractForm::class, $contract, [
            'allow_associate' => false,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contract = $form->getData();
            $contractRepository->save($contract, true);

            return $this->redirectToRoute('contract', [
                'uid' => $contract->getUid(),
            ]);
        }

        return $this->render('contracts/edit.html.twig', [
            'organization' => $organization,
            'contract' => $contract,
            'form' => $form,
        ]);
    }
}
