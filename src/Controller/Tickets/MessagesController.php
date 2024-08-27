<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Message;
use App\Entity\Ticket;
use App\Entity\TimeSpent;
use App\Message\SendMessageEmail;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Repository\TimeSpentRepository;
use App\Security\Authorizer;
use App\Service\ContractTimeAccounting;
use App\Service\TicketTimeline;
use App\TicketActivity\MessageEvent;
use App\TicketActivity\TicketEvent;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Time;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MessagesController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/messages/new', name: 'create ticket message', methods: ['POST'])]
    public function create(
        Ticket $ticket,
        Request $request,
        MessageRepository $messageRepository,
        OrganizationRepository $organizationRepository,
        TicketRepository $ticketRepository,
        TimeSpentRepository $timeSpentRepository,
        ContractTimeAccounting $contractTimeAccounting,
        TicketTimeline $ticketTimeline,
        Authorizer $authorizer,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer,
        TranslatorInterface $translator,
        MessageBusInterface $bus,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets:messages', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        /** @var \App\Entity\User */
        $user = $this->getUser();

        $organization = $ticket->getOrganization();

        $messageContent = $request->request->getString('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        $minutesSpent = $request->request->getInt('timeSpent', 0);
        if ($minutesSpent < 0) {
            $minutesSpent = 0;
        }

        $answerType = $request->request->getString('answerType', 'normal');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create ticket message', $csrfToken)) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'minutesSpent' => $minutesSpent,
                'answerType' => $answerType,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $isConfidential = $answerType === 'confidential';
        $canPostConfidential = $authorizer->isGranted('orga:create:tickets:messages:confidential', $organization);
        if ($isConfidential && !$canPostConfidential) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'answerType' => $answerType,
                'minutesSpent' => $minutesSpent,
                'errors' => [
                    'answerType' => $translator->trans('message.cannot_confidential', [], 'errors'),
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
                'answerType' => $answerType,
                'minutesSpent' => $minutesSpent,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $ticket->setUpdatedAt(Time::now());
        $ticket->setUpdatedBy($user);

        $ticketRepository->save($ticket, true);
        $messageRepository->save($message, true);

        $messageEvent = new MessageEvent($message);

        if ($user == $ticket->getAssignee() && $answerType === 'solution') {
            $eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED_SOLUTION);
        } elseif ($user == $ticket->getRequester() && $answerType === 'solution approval') {
            $eventDispatcher->dispatch($messageEvent, MessageEvent::APPROVED_SOLUTION);
        } elseif ($user == $ticket->getRequester() && $answerType === 'solution refusal') {
            $eventDispatcher->dispatch($messageEvent, MessageEvent::REFUSED_SOLUTION);
        } else {
            $eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED);
        }

        if ($minutesSpent > 0 && $authorizer->isGranted('orga:create:tickets:time_spent', $organization)) {
            $contract = $ticket->getOngoingContract();

            if ($contract) {
                $timeSpent = $contractTimeAccounting->accountTime($contract, $minutesSpent);
                $timeSpent->setTicket($ticket);
                $timeSpentRepository->save($timeSpent, true);

                // Calculate the remaining time that is not accounted (i.e.
                // because there wasn't enough time in the contract).
                $remainingUnaccountedTime = $minutesSpent - $timeSpent->getRealTime();
                if ($remainingUnaccountedTime > 0) {
                    $timeSpent = new TimeSpent();
                    $timeSpent->setTicket($ticket);
                    $timeSpent->setTime($remainingUnaccountedTime);
                    $timeSpent->setRealTime($remainingUnaccountedTime);
                    $timeSpentRepository->save($timeSpent, true);
                }
            } else {
                $timeSpent = new TimeSpent();
                $timeSpent->setTicket($ticket);
                $timeSpent->setTime($minutesSpent);
                $timeSpent->setRealTime($minutesSpent);
                $timeSpentRepository->save($timeSpent, true);
            }
        }

        $bus->dispatch(new SendMessageEmail($message->getId()));

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
