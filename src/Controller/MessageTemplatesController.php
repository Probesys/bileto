<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\MessageTemplate;
use App\Form;
use App\Repository\MessageTemplateRepository;
use App\Service\Sorter\MessageTemplateSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MessageTemplatesController extends BaseController
{
    #[Route('/message-templates', name: 'message templates', methods: ['GET', 'HEAD'])]
    public function index(
        MessageTemplateRepository $messageTemplateRepository,
        MessageTemplateSorter $messageTemplateSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:templates');

        $messageTemplates = $messageTemplateRepository->findAll();
        $messageTemplateSorter->sort($messageTemplates);

        return $this->render('message_templates/index.html.twig', [
            'messageTemplates' => $messageTemplates,
        ]);
    }

    #[Route('/message-templates/new', name: 'new message template')]
    public function new(
        Request $request,
        MessageTemplateRepository $messageTemplateRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:templates');

        $messageTemplate = new MessageTemplate();

        $form = $this->createNamedForm('message_template', Form\MessageTemplateForm::class, $messageTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $messageTemplate = $form->getData();
            $messageTemplateRepository->save($messageTemplate, true);

            return $this->redirectToRoute('message templates');
        }

        return $this->render('message_templates/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/message-templates/{uid:messageTemplate}/edit', name: 'edit message template')]
    public function edit(
        MessageTemplate $messageTemplate,
        Request $request,
        MessageTemplateRepository $messageTemplateRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:templates');

        $form = $this->createNamedForm('message_template', Form\MessageTemplateForm::class, $messageTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $messageTemplate = $form->getData();
            $messageTemplateRepository->save($messageTemplate, true);

            return $this->redirectToRoute('message templates');
        }

        return $this->render('message_templates/edit.html.twig', [
            'messageTemplate' => $messageTemplate,
            'form' => $form,
        ]);
    }
}
