<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\EntityEvent;
use App\Entity\Message;
use App\Entity\Ticket;
use App\Repository\EntityEventRepository;
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

    /**
     * @return array<Message|EntityEvent>
     */
    public function build(Ticket $ticket): array
    {
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

        $events = $this->entityEventRepository->findBy([
            'entityType' => Ticket::class,
            'entityId' => $ticket->getId(),
            'type' => 'update',
        ]);

        $items = array_merge($messages, $events);

        uasort($items, function ($i1, $i2) {
            $createdAt1 = $i1->getCreatedAt();
            $createdAt2 = $i2->getCreatedAt();

            if ($createdAt1 < $createdAt2) {
                return -1;
            } elseif ($createdAt1 > $createdAt2) {
                return 1;
            } elseif ($i1->getTimelineType() === 'message') {
                return -1;
            } elseif ($i1->getTimelineType() === 'event') {
                return 1;
            } else {
                return 0;
            }
        });

        return $items;
    }
}
