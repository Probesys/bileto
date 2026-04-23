<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Message;
use App\Repository;
use App\Service;
use App\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanDataHandler
{
    public function __construct(
        private Repository\EntityEventRepository $entityEventRepository,
        private Repository\TokenRepository $tokenRepository,
        private Repository\SessionLogRepository $sessionLogRepository,
        private Repository\UserRepository $userRepository,
        private Service\UserService $userService,
        private LoggerInterface $logger,
        #[Autowire(env: 'int:APP_USERS_INACTIVITY_TIME')]
        private int $usersInactivityMonths,
        #[Autowire(env: 'APP_USERS_INACTIVITY_AUTO')]
        private string $usersInactivityAuto,
    ) {
    }

    public function __invoke(Message\CleanData $message): void
    {
        $this->cleanInvalidTokens();
        $this->cleanOldSessionLogs();
        $this->cleanExpiredEntityEvents();
        $this->cleanInactiveUsers();
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

    private function cleanInactiveUsers(): void
    {
        if ($this->usersInactivityMonths <= 0) {
            return;
        }

        if ($this->usersInactivityAuto === 'none') {
            return;
        }

        if (!in_array($this->usersInactivityAuto, ['anonymize', 'delete'], true)) {
            $this->logger->warning(
                "[CleanData] Unknown APP_USERS_INACTIVITY_AUTO value " .
                "'{$this->usersInactivityAuto}', skipping inactive users cleanup"
            );
            return;
        }

        $inactiveUsers = $this->userRepository->findInactive($this->usersInactivityMonths);
        $countProcessed = 0;

        foreach ($inactiveUsers as $user) {
            try {
                if ($this->usersInactivityAuto === 'anonymize') {
                    $this->userService->anonymize($user);
                } else {
                    $this->userRepository->remove($user, true);
                }
                $countProcessed++;
            } catch (\Throwable $e) {
                $this->logger->error(
                    "[CleanData] Failed to {$this->usersInactivityAuto} " .
                    "inactive user #{$user->getId()}: {$e->getMessage()}"
                );
            }
        }

        if ($countProcessed > 0) {
            $action = $this->usersInactivityAuto === 'anonymize' ? 'anonymized' : 'deleted';
            $this->logger->notice("[CleanData] {$countProcessed} inactive user(s) {$action}");
        }
    }
}
