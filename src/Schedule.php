<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App;

use App\Message;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $schedule = new SymfonySchedule();

        $schedule->stateful($this->cache);
        $schedule->processOnlyLastMissedRun(true);

        $schedule->add(RecurringMessage::every('1 minute', new Message\FetchMailboxes()));
        $schedule->add(RecurringMessage::every('1 minute', new Message\CreateTicketsFromMailboxEmails()));

        $schedule->add(RecurringMessage::every('12 hours', new Message\ProcessPreviouslyResolvedTickets()));
        $schedule->add(RecurringMessage::every('12 hours', new Message\SynchronizeLdap()));

        $schedule->add(RecurringMessage::every('24 hours', new Message\CleanData()));

        return $schedule;
    }
}
