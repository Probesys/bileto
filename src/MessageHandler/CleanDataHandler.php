<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Message;
use App\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanDataHandler
{
    public function __construct(
        private Repository\TokenRepository $tokenRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Message\CleanData $message): void
    {
        $tokens = $this->tokenRepository->findAll();
        $tokensToRemove = [];

        foreach ($tokens as $token) {
            if (!$token->isValid()) {
                $tokensToRemove[] = $token;
            }
        }

        if ($tokensToRemove) {
            $this->tokenRepository->remove($tokensToRemove, true);

            $countRemovedTokens = count($tokensToRemove);
            $this->logger->notice("[CleanData] {$countRemovedTokens} expired token(s) deleted");
        }
    }
}
