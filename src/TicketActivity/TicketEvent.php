<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Entity\Ticket;
use Symfony\Contracts\EventDispatcher\Event;

class TicketEvent extends Event
{
    // To be used each time a ticket is created (alongside with MessageEvent::CREATED).
    public const CREATED = 'ticket.created';

    // To be used each time the assignee of a ticket change.
    public const ASSIGNED = 'ticket.assigned';

    // To be used when a ticket's status becomes "resolved".
    public const RESOLVED = 'ticket.resolved';

    private Ticket $ticket;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }
}
