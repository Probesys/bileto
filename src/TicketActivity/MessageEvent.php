<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Entity\Message;
use Symfony\Contracts\EventDispatcher\Event;

class MessageEvent extends Event
{
    // To be used each time a message is created.
    public const CREATED = 'message.created';

    // To be used each time a answer without solution is created.
    public const CREATED_ANSWER = 'message.created.answer';

    // To be used when an answer is posted as a solution.
    public const CREATED_SOLUTION = 'message.created.solution';

    // To be used when an answer approves a solution.
    public const APPROVED_SOLUTION = 'message.created.solution.approved';

    // To be used when an answer refuses a solution.
    public const REFUSED_SOLUTION = 'message.created.solution.refused';

    private Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
