<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
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
use App\Service\ContractBilling;
use App\Service\TicketTimeline;
use App\TicketActivity\MessageEvent;
use App\TicketActivity\TicketEvent;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Time;
use Symfony\Bundle\SecurityBundle\Security;
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
    #[Route('/tickets/{uid}/messages/new', name: 'create ticket message', methods: ['POST'])]
    public function create(
        Ticket $ticket,
        Request $request,
        MessageRepository $messageRepository,
        OrganizationRepository $organizationRepository,
        TicketRepository $ticketRepository,
        TimeSpentRepository $timeSpentRepository,
        ContractBilling $contractBilling,
        TicketTimeline $ticketTimeline,
        Security $security,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer,
        TranslatorInterface $translator,
        MessageBusInterface $bus,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:create:tickets:messages', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $messageContent = $request->request->getString('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        $isConfidential = $request->request->getBoolean('isConfidential', false);

        $minutesSpent = $request->request->getInt('timeSpent', 0);
        if ($minutesSpent < 0) {
            $minutesSpent = 0;
        }

        $answerAction = $request->request->getString('answerAction', 'none');

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
                'isConfidential' => $isConfidential,
                'answerAction' => $answerAction,
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
                'answerAction' => $answerAction,
                'minutesSpent' => $minutesSpent,
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
                'answerAction' => $answerAction,
                'minutesSpent' => $minutesSpent,
                'isConfidential' => $isConfidential,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $ticket->setUpdatedAt(Time::now());
        $ticket->setUpdatedBy($user);

        $ticketRepository->save($ticket, true);
        $messageRepository->save($message, true);

        $messageEvent = new MessageEvent($message);

        if ($user == $ticket->getAssignee() && $answerAction === 'new solution') {
            $eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED_SOLUTION);
        } else {
            $eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED);
        }

        if ($minutesSpent > 0 && $security->isGranted('orga:create:tickets:time_spent', $organization)) {
            $contract = $ticket->getOngoingContract();

            if ($contract) {
                $timeSpent = $contractBilling->chargeTime($contract, $minutesSpent);
                $timeSpent->setTicket($ticket);
                $timeSpentRepository->save($timeSpent, true);

                // Calculate the remaining time that is not charged (e.g.
                // because there wasn't enough time in the contract).
                $remainingNotChargedTime = $minutesSpent - $timeSpent->getRealTime();
                if ($remainingNotChargedTime > 0) {
                    $timeSpent = new TimeSpent();
                    $timeSpent->setTicket($ticket);
                    $timeSpent->setTime($remainingNotChargedTime);
                    $timeSpent->setRealTime($remainingNotChargedTime);
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
