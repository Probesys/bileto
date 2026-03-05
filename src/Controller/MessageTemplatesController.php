<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageTemplatesController extends BaseController
{
    public function __construct(
        private readonly Repository\MessageTemplateRepository $messageTemplateRepository,
        private readonly Service\Sorter\MessageTemplateSorter $messageTemplateSorter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/message-templates', name: 'message templates', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:templates');
        $messageTemplates = $this->messageTemplateRepository->findAll();
        $this->messageTemplateSorter->sort($messageTemplates);
        return $this->render('message_templates/index.html.twig', [
            'messageTemplates' => $messageTemplates,
        ]);
    }

    #[Route('/message-templates/new', name: 'new message template')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:templates');

        $messageTemplate = new Entity\MessageTemplate();

        $form = $this->createNamedForm('message_template', Form\MessageTemplateForm::class, $messageTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $messageTemplate = $form->getData();
            $this->messageTemplateRepository->save($messageTemplate, true);

            return $this->redirectToRoute('message templates');
        }

        return $this->render('message_templates/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/message-templates/{uid:messageTemplate}/edit', name: 'edit message template')]
    public function edit(Entity\MessageTemplate $messageTemplate, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:templates');

        $form = $this->createNamedForm('message_template', Form\MessageTemplateForm::class, $messageTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $messageTemplate = $form->getData();
            $this->messageTemplateRepository->save($messageTemplate, true);

            $this->addFlash('success', new TranslatableMessage('notifications.saved'));

            return $this->redirectToRoute('edit message template', [
                'uid' => $messageTemplate->getUid(),
            ]);
        }

        return $this->render('message_templates/edit.html.twig', [
            'messageTemplate' => $messageTemplate,
            'form' => $form,
        ]);
    }

    #[Route('/message-templates/{uid:messageTemplate}/deletion', name: 'delete message template', methods: ['POST'])]
    public function delete(Entity\MessageTemplate $messageTemplate, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:templates');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete message template', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', domain: 'errors'));
            return $this->redirectToRoute('edit message template', [
                'uid' => $messageTemplate->getUid(),
            ]);
        }

        $this->messageTemplateRepository->remove($messageTemplate, flush: true);

        return $this->redirectToRoute('message templates');
    }
}
