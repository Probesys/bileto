<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Scheduler;

use App\Message\CreateTicketsFromMailboxEmails;
use App\Message\FetchMailboxes;
use App\Message\SynchronizeLdap;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class DefaultScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        $schedule->add(RecurringMessage::every('1 minute', new FetchMailboxes()));
        $schedule->add(RecurringMessage::every('1 minute', new CreateTicketsFromMailboxEmails()));

        $schedule->add(RecurringMessage::every('12 hours', new SynchronizeLdap()));

        return $schedule;
    }
}
