<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Ticket;
use App\Repository\EntityEventRepository;
use App\Utils\Timeline;
use Symfony\Bundle\SecurityBundle\Security;

class TicketTimeline
{
    private EntityEventRepository $entityEventRepository;

    private Security $security;

    public function __construct(
        EntityEventRepository $entityEventRepository,
        Security $security,
    ) {
        $this->entityEventRepository = $entityEventRepository;
        $this->security = $security;
    }

    public function build(Ticket $ticket): Timeline
    {
        $timeline = new Timeline();

        $organization = $ticket->getOrganization();
        $includeConfidential = $this->security->isGranted(
            'orga:see:tickets:messages:confidential',
            $organization,
        );
        if ($includeConfidential) {
            $messages = $ticket->getMessages()->toArray();
        } else {
            $messages = $ticket->getMessagesWithoutConfidential()->toArray();
        }

        $timeline->addItems($messages);

        /** @var \App\Entity\User $user */
        $user = $this->security->getUser();
        if (!$user->areEventsHidden()) {
            $events = $this->entityEventRepository->findBy([
                'entityType' => Ticket::class,
                'entityId' => $ticket->getId(),
                'type' => 'update',
            ]);

            $timeline->addItems($events);
        }

        return $timeline;
    }
}
