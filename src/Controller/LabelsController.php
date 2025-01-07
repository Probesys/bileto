<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Label;
use App\Form;
use App\Repository\LabelRepository;
use App\Service\Sorter\LabelSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    #[Route('/labels/new', name: 'new label')]
    public function new(
        Request $request,
        LabelRepository $labelRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $label = new Label();
        $form = $this->createNamedForm('label', Form\LabelForm::class, $label);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->getData();
            $labelRepository->save($label, true);

            return $this->redirectToRoute('labels');
        }

        return $this->render('labels/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/labels/{uid:label}/edit', name: 'edit label')]
    public function edit(
        Label $label,
        Request $request,
        LabelRepository $labelRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $form = $this->createNamedForm('label', Form\LabelForm::class, $label);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $label = $form->getData();
            $labelRepository->save($label, true);

            return $this->redirectToRoute('labels');
        }

        return $this->render('labels/edit.html.twig', [
            'label' => $label,
            'form' => $form,
        ]);
    }

    #[Route('/labels/{uid:label}/deletion', name: 'delete label', methods: ['POST'])]
    public function delete(
        Label $label,
        Request $request,
        LabelRepository $labelRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:labels');

        $csrfToken = $request->request->getString('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete label', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit label', ['uid' => $label->getUid()]);
        }

        $labelRepository->remove($label, true);

        return $this->redirectToRoute('labels');
    }
}
