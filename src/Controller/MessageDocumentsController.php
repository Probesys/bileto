<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\MessageDocument;
use App\Repository\MessageDocumentRepository;
use App\Service\MessageDocumentStorage;
use App\Service\MessageDocumentStorageError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessageDocumentsController extends BaseController
{
    #[Route('/messages/documents/new', name: 'create message document', methods: ['POST'])]
    public function create(
        Request $request,
        MessageDocumentRepository $messageDocumentRepository,
        MessageDocumentStorage $messageDocumentStorage,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets:messages', 'any');

        $file = $request->files->get('document');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create message document', $csrfToken)) {
            return new JsonResponse([
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ], 400);
        }

        if (!($file instanceof UploadedFile)) {
            return new JsonResponse([
                'error' => $translator->trans('message_document.required', [], 'errors'),
            ], 400);
        }

        try {
            $messageDocument = $messageDocumentStorage->store($file, $file->getClientOriginalName());
        } catch (MessageDocumentStorageError $e) {
            if ($e->getCode() === MessageDocumentStorageError::REJECTED_MIMETYPE) {
                return new JsonResponse([
                    'error' => $translator->trans('message_document.mimetype.rejected', [], 'errors'),
                    'description' => $e->getMessage(),
                ], 400);
            } else {
                return new JsonResponse([
                    'error' => $translator->trans('message_document.server_error', [], 'errors'),
                    'description' => $e->getMessage(),
                ], 500);
            }
        }

        $messageDocumentRepository->save($messageDocument, true);

        $urlShow = $this->generateUrl(
            'message document',
            [
                'uid' => $messageDocument->getUid(),
                'extension' => $messageDocument->getExtension(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse([
            'uid' => $messageDocument->getUid(),
            'name' => $messageDocument->getName(),
            'urlShow' => $urlShow,
        ]);
    }
}
