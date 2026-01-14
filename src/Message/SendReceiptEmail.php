<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Message;

class SendReceiptEmail
{
    public function __construct(
        private int $ticketId,
    ) {
    }

    public function getTicketId(): int
    {
        return $this->ticketId;
    }
}
