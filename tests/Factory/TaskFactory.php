<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Task;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Task>
 */
final class TaskFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        $user = UserFactory::new();
        $date = \DateTimeImmutable::createFromMutable(self::faker()->dateTime());
        $startAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTime());
        $endAt = $startAt->modify("+1 hour");

        return [
            'createdAt' => $date,
            'createdBy' => $user,
            'updatedAt' => $date,
            'updatedBy' => $user,
            'ticket' => TicketFactory::new(),
            'label' => self::faker()->words(3, true),
            'startAt' => $startAt,
            'endAt' => $endAt,
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Task::class;
    }

    public function unfinished(): self
    {
        return $this->with([
            'finishedAt' => null,
        ]);
    }
}
