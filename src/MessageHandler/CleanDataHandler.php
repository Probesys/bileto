<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Message;
use App\Repository;
use App\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanDataHandler
{
    public function __construct(
        private Repository\EntityEventRepository $entityEventRepository,
        private Repository\TokenRepository $tokenRepository,
        private Repository\SessionLogRepository $sessionLogRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Message\CleanData $message): void
    {
        $this->cleanInvalidTokens();
        $this->cleanOldSessionLogs();
        $this->cleanExpiredEntityEvents();
    }

    private function cleanInvalidTokens(): void
    {
        $now = Utils\Time::now();
        $countRemovedTokens = $this->tokenRepository->removeInvalidSince($now);

        if ($countRemovedTokens > 0) {
            $this->logger->notice("[CleanData] {$countRemovedTokens} expired token(s) deleted");
        }
    }

    private function cleanOldSessionLogs(): void
    {
        $oneYearAgo = Utils\Time::ago(6, 'months');
        $countRemovedLogs = $this->sessionLogRepository->removeOlderThan($oneYearAgo);

        if ($countRemovedLogs > 0) {
            $this->logger->notice("[CleanData] {$countRemovedLogs} session log(s) deleted");
        }
    }

    private function cleanExpiredEntityEvents(): void
    {
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $countRemovedEvents = $this->entityEventRepository->removeExpiredOlderThan($oneWeekAgo);

        if ($countRemovedEvents > 0) {
            $this->logger->notice("[CleanData] {$countRemovedEvents} entity event(s) deleted");
        }
    }
}
