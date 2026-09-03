<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use Psr\Log\LoggerInterface;

/**
 * The Logger service allows to log messages in a structured way (JSON).
 */
class Logger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param Entity\EntityInterface[] $entities
     * @param array<string, mixed> $context
     */
    public function critical(
        string $message,
        array $entities = [],
        string $caller = '',
        array $context = [],
    ): void {
        $this->logger->critical($message, $this->buildContext($entities, $caller, $context));
    }

    /**
     * @param Entity\EntityInterface[] $entities
     * @param array<string, mixed> $context
     */
    public function error(
        string $message,
        array $entities = [],
        string $caller = '',
        array $context = [],
    ): void {
        $this->logger->error($message, $this->buildContext($entities, $caller, $context));
    }

    /**
     * @param Entity\EntityInterface[] $entities
     * @param array<string, mixed> $context
     */
    public function warning(
        string $message,
        array $entities = [],
        string $caller = '',
        array $context = []
    ): void {
        $this->logger->warning($message, $this->buildContext($entities, $caller, $context));
    }

    /**
     * @param Entity\EntityInterface[] $entities
     * @param array<string, mixed> $context
     */
    public function notice(
        string $message,
        array $entities = [],
        string $caller = '',
        array $context = [],
    ): void {
        $this->logger->notice($message, $this->buildContext($entities, $caller, $context));
    }

    /**
     * @param Entity\EntityInterface[] $entities
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildContext(array $entities, string $caller, array $context): array
    {
        $structuredEntities = [];

        foreach ($entities as $entity) {
            $structuredEntities[$entity::class] = $entity->getId();
        }

        if (!$caller) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $callingTrace = $backtrace[2];
            $caller = '';
            if (isset($callingTrace['class'])) {
                $caller = "{$callingTrace['class']}::";
            }
            $caller .= $callingTrace['function'];
        }

        return array_merge($context, [
            'caller' => $caller,
            'entities' => $structuredEntities,
        ]);
    }
}
