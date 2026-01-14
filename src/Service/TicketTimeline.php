<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;
use App\Security as AppSecurity;
use App\Utils;
use Symfony\Bundle\SecurityBundle\Security;

class TicketTimeline
{
    public function __construct(
        private Repository\EntityEventRepository $entityEventRepository,
        private AppSecurity\Authorizer $authorizer,
        private Security $security,
    ) {
    }

    public function build(Entity\Ticket $ticket): Utils\Timeline
    {
        $timeline = new Utils\Timeline();

        $organization = $ticket->getOrganization();

        $includeConfidential = $this->authorizer->isGranted(
            'orga:see:tickets:messages:confidential',
            $organization,
        );

        $messages = $ticket->getMessages(confidential: $includeConfidential)->toArray();

        $timeline->addItems($messages);

        if (
            $this->authorizer->isGranted('orga:see:tickets:time_spent:accounted', $organization) ||
            $this->authorizer->isGranted('orga:see:tickets:time_spent:real', $organization)
        ) {
            $timeSpents = $ticket->getTimeSpents()->getValues();
            $timeline->addItems($timeSpents);
        }

        /** @var \App\Entity\User $user */
        $user = $this->security->getUser();
        if (!$user->areEventsHidden()) {
            $events = $this->entityEventRepository->findBy([
                'entityType' => Entity\Ticket::class,
                'entityId' => $ticket->getId(),
                'type' => 'update',
            ]);

            if (!$this->authorizer->isGranted('orga:see:tickets:contracts', $organization)) {
                // Make sure to remove events referencing contracts if the user
                // doesn't have the permission to see them.
                $events = array_filter($events, function (Entity\EntityEvent $event): bool {
                    return !$event->refersTo('ongoingContract');
                });
            }

            $timeline->addItems($events);
        }

        return $timeline;
    }
}
