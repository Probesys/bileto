<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Message;
use App\Entity\Ticket;
use App\Message\SendMessageEmail;
use App\Repository\MessageRepository;
use App\Repository\MessageDocumentRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Service\TicketTimeline;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Time;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessagesController extends BaseController
{
    #[Route('/tickets/{uid}/messages/new', name: 'create ticket message', methods: ['POST'])]
    public function create(
        Ticket $ticket,
        Request $request,
        MessageRepository $messageRepository,
        MessageDocumentRepository $messageDocumentRepository,
        OrganizationRepository $organizationRepository,
        TicketRepository $ticketRepository,
        TicketTimeline $ticketTimeline,
        Security $security,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer,
        TranslatorInterface $translator,
        MessageBusInterface $bus,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:create:tickets:messages', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        /** @var string $messageContent */
        $messageContent = $request->request->get('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        /** @var boolean $isConfidential */
        $isConfidential = $request->request->getBoolean('isConfidential', false);

        if ($security->isGranted('orga:update:tickets:status', $organization)) {
            /** @var string $status */
            $status = $request->request->get('status', '');

            /** @var boolean $isSolution */
            $isSolution = $request->request->getBoolean('isSolution', false);
        } else {
            $status = $ticket->getStatus();
            $isSolution = false;
        }

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $statuses = Ticket::getStatusesWithLabels();
        if ($ticket->getStatus() !== 'new') {
            unset($statuses['new']);
        }

        if (!$this->isCsrfTokenValid('create ticket message', $csrfToken)) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        if ($isConfidential && !$security->isGranted('orga:create:tickets:messages:confidential', $organization)) {
            // We don't want to force $isConfidential to false as we do for the
            // other parameters. If there is a bug in the frontend that shows
            // the "mark as confidential" checkbox without the correct
            // permission, the user will expect its message to be confidential.
            // This can cause a privacy issue.
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'errors' => [
                    'isConfidential' => $translator->trans('message.cannot_confidential', [], 'errors'),
                ],
            ]);
        }

        $message = new Message();
        $message->setContent($messageContent);
        $message->setTicket($ticket);
        $message->setIsConfidential($isConfidential);
        $message->setVia('webapp');

        $errors = $validator->validate($message);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        if ($message->isConfidential()) {
            $isSolution = false;
        }

        if ($ticket->isFinished()) {
            $status = $ticket->getStatus();
            $isSolution = false;
        }

        if ($isSolution) {
            $ticket->setSolution($message);
            $status = 'resolved';
        }

        $initialStatus = $ticket->getStatus();
        $ticket->setStatus($status);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            $ticket->setStatus($initialStatus);
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $ticket->setUpdatedAt(Time::now());
        $ticket->setUpdatedBy($user);

        $messageRepository->save($message, true);
        $ticketRepository->save($ticket, true);

        $messageDocuments = $messageDocumentRepository->findBy([
            'createdBy' => $user,
            'message' => null,
        ]);

        foreach ($messageDocuments as $messageDocument) {
            $messageDocument->setMessage($message);
            $messageDocumentRepository->save($messageDocument, true);
        }

        $bus->dispatch(new SendMessageEmail($message->getId()));

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
