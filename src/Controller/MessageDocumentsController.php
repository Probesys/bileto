<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Allow to upload and download MessageDocuments.
 *
 * @see docs/developers/document-upload.md
 */
class MessageDocumentsController extends BaseController
{
    public function __construct(
        private readonly Repository\MessageDocumentRepository $messageDocumentRepository,
        private readonly Service\MessageDocumentStorage $messageDocumentStorage,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/messages/documents/new', name: 'create message document', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:see', 'any');

        $file = $request->files->get('document');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create message document', $csrfToken)) {
            return new JsonResponse([
                'error' => $this->translator->trans('csrf.invalid', [], 'errors'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!($file instanceof UploadedFile)) {
            return new JsonResponse([
                'error' => $this->translator->trans('message_document.required', [], 'errors'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (
            $file->getError() === \UPLOAD_ERR_INI_SIZE ||
            $file->getError() === \UPLOAD_ERR_FORM_SIZE
        ) {
            return new JsonResponse([
                'error' => $this->translator->trans('message_document.too_large', [], 'errors'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$file->isValid()) {
            return new JsonResponse([
                'error' => $this->translator->trans('message_document.server_error', [], 'errors'),
                'description' => $file->getErrorMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $messageDocument = $this->messageDocumentStorage->store($file, $file->getClientOriginalName());
        } catch (Service\MessageDocumentStorageError $e) {
            if ($e->getCode() === Service\MessageDocumentStorageError::REJECTED_MIMETYPE) {
                return new JsonResponse([
                    'error' => $this->translator->trans('message_document.mimetype.rejected', [], 'errors'),
                    'description' => $e->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
            } else {
                return new JsonResponse([
                    'error' => $this->translator->trans('message_document.server_error', [], 'errors'),
                    'description' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $this->messageDocumentRepository->save($messageDocument, true);

        $urlShow = $this->generateUrl(
            'message document',
            [
                'uid' => $messageDocument->getUid(),
                'extension' => $messageDocument->getExtension(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $urlDelete = $this->generateUrl(
            'delete message document',
            [
                'uid' => $messageDocument->getUid(),
            ],
        );

        return new JsonResponse([
            'uid' => $messageDocument->getUid(),
            'urlShow' => $urlShow,
        ]);
    }

    #[Route(
        '/messages/documents/{uid:messageDocument}.{extension}',
        name: 'message document',
        methods: ['GET', 'HEAD']
    )]
    public function show(Entity\MessageDocument $messageDocument, string $extension): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $message = $messageDocument->getMessage();

        if (!$messageDocument->isCreatedBy($user)) {
            if ($message === null) {
                // The message of the document is not posted yet, only its author
                // can see it.
                throw $this->createAccessDeniedException();
            } else {
                // The message of the document is posted, check that the user has
                // the permissions to see the message.
                $ticket = $message->getTicket();

                if ($message->isConfidential()) {
                    $this->denyAccessUnlessGranted('orga:see:tickets:messages:confidential', $ticket);
                } else {
                    $this->denyAccessUnlessGranted('orga:see', $ticket);
                }
            }
        }

        // The extension parameter is only decorative, but at least check that
        // it corresponds to the real extension!
        if ($extension !== $messageDocument->getExtension()) {
            throw $this->createNotFoundException('The file does not exist.');
        }

        try {
            $content = $this->messageDocumentStorage->read($messageDocument);
            $contentLength = $this->messageDocumentStorage->size($messageDocument);
        } catch (Service\MessageDocumentStorageError $e) {
            throw $this->createNotFoundException('The file does not exist.');
        }

        $name = rawurlencode($messageDocument->getName());
        $mimetype = $messageDocument->getMimetype();
        if (str_starts_with($mimetype, 'image/') && $mimetype !== 'image/svg+xml') {
            $contentDisposition = "inline; filename=\"{$name}\"";
        } else {
            $contentDisposition = "attachment; filename=\"{$name}\"";
        }

        return new Response(
            $content,
            Response::HTTP_OK,
            [
                'Content-Disposition' => $contentDisposition,
                'Content-Length' => $contentLength,
                'Content-Type' => $mimetype,
            ]
        );
    }

    #[Route('/messages/documents/{uid:messageDocument}/deletion', name: 'delete message document', methods: ['POST'])]
    public function delete(Entity\MessageDocument $messageDocument, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$messageDocument->isCreatedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete message document', $csrfToken)) {
            return new JsonResponse([
                'error' => $this->translator->trans('csrf.invalid', [], 'errors'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->messageDocumentRepository->remove($messageDocument, true);

        $countSameHashDocuments = $this->messageDocumentRepository->countByHash($messageDocument->getHash());
        if ($countSameHashDocuments === 0) {
            $this->messageDocumentStorage->remove($messageDocument);
        }

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
            'urlShow' => $urlShow,
        ]);
    }

    #[Route('/messages/documents', name: 'message documents', methods: ['GET', 'HEAD'])]
    public function index(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $conditions = [
            'createdBy' => $user,
        ];

        $filter = $request->query->get('filter', '');
        if ($filter === 'unattached') {
            $conditions['message'] = null;
        }

        $messageDocuments = $this->messageDocumentRepository->findBy(
            $conditions,
            ['createdAt' => 'ASC'],
        );

        return $this->render('message_documents/index.html.twig', [
            'messageDocuments' => $messageDocuments,
        ]);
    }
}
