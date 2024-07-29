<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Label;
use App\Form;
use App\Repository\LabelRepository;
use App\Service\Sorter\LabelSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LabelsController extends BaseController
{
    #[Route('/labels', name: 'labels', methods: ['GET', 'HEAD'])]
    public function index(LabelRepository $labelRepository, LabelSorter $labelSorter): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $labels = $labelRepository->findAll();
        $labelSorter->sort($labels);

        return $this->render('labels/index.html.twig', [
            'labels' => $labels,
        ]);
    }

    #[Route('/labels/new', name: 'new label', methods: ['GET', 'HEAD'])]
    public function new(
        LabelRepository $labelRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $label = new Label();
        $form = $this->createForm(Form\Type\LabelType::class, $label);

        return $this->render('labels/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/labels/new', name: 'create label', methods: ['POST'])]
    public function create(
        Request $request,
        LabelRepository $labelRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $form = $this->createForm(Form\Type\LabelType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('labels/new.html.twig', [
                'form' => $form,
            ]);
        }

        $label = $form->getData();
        $labelRepository->save($label, true);

        return $this->redirectToRoute('labels');
    }

    #[Route('/labels/{uid:label}/edit', name: 'edit label', methods: ['GET', 'HEAD'])]
    public function edit(Label $label): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $form = $this->createForm(Form\Type\LabelType::class, $label);

        return $this->render('labels/edit.html.twig', [
            'label' => $label,
            'form' => $form,
        ]);
    }

    #[Route('/labels/{uid:label}/edit', name: 'update label', methods: ['POST'])]
    public function update(
        Label $label,
        Request $request,
        LabelRepository $labelRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $form = $this->createForm(Form\Type\LabelType::class, $label);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('labels/edit.html.twig', [
                'label' => $label,
                'form' => $form,
            ]);
        }

        $label = $form->getData();
        $labelRepository->save($label, true);

        return $this->redirectToRoute('labels');
    }
}
