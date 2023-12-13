<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Entity\Message;
use Symfony\Contracts\EventDispatcher\Event;

class MessageEvent extends Event
{
    // To be used each time an answer without solution is created.
    public const CREATED = 'message.created';

    // To be used when an answer is posted as a solution.
    public const CREATED_SOLUTION = 'message.created.solution';

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
