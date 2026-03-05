<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class LabelsController extends BaseController
{
    public function __construct(
        private readonly Repository\LabelRepository $labelRepository,
        private readonly Service\Sorter\LabelSorter $labelSorter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/labels', name: 'labels', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:labels');
        $labels = $this->labelRepository->findAll();
        $this->labelSorter->sort($labels);
        return $this->render('labels/index.html.twig', [
            'labels' => $labels,
        ]);
    }

    #[Route('/labels/new', name: 'new label')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $label = new Entity\Label();
        $form = $this->createNamedForm('label', Form\LabelForm::class, $label);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->getData();
            $this->labelRepository->save($label, true);

            return $this->redirectToRoute('labels');
        }

        return $this->render('labels/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/labels/{uid:label}/edit', name: 'edit label')]
    public function edit(Entity\Label $label, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $form = $this->createNamedForm('label', Form\LabelForm::class, $label);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->getData();
            $this->labelRepository->save($label, true);

            return $this->redirectToRoute('labels');
        }

        return $this->render('labels/edit.html.twig', [
            'label' => $label,
            'form' => $form,
        ]);
    }

    #[Route('/labels/{uid:label}/deletion', name: 'delete label', methods: ['POST'])]
    public function delete(Entity\Label $label, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $csrfToken = $request->request->getString('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete label', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit label', ['uid' => $label->getUid()]);
        }

        $this->labelRepository->remove($label, true);

        return $this->redirectToRoute('labels');
    }
}
